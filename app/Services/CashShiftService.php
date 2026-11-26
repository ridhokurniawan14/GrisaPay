<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Models\CashShift;
use App\Models\User;
use App\Support\PendingNotifier;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Buka/tutup/tinjau shift kas. Kasir wajib membuka shift sebelum mencatat pembayaran. */
class CashShiftService
{
    public function current(User $user): ?CashShift
    {
        return CashShift::query()->where('user_id', $user->id)->where('status', CashShift::OPEN)->latest('id')->first();
    }

    /** Shift wajib hanya untuk pengguna dengan izin shift kas (kasir) dan bila tidak dimatikan di config. */
    public function isRequiredFor(User $user): bool
    {
        return (bool) config('grisapay.require_shift', true) && $user->can(PermissionName::CashSessionsManage->value);
    }

    /** @throws DomainException bila shift wajib tetapi belum dibuka */
    public function requireOpen(User $user): ?CashShift
    {
        $shift = $this->current($user);

        if ($shift === null && $this->isRequiredFor($user)) {
            throw new DomainException('Buka shift kas dulu sebelum mencatat pembayaran.');
        }

        return $shift;
    }

    public function open(User $user, int $openingCash): CashShift
    {
        if ($openingCash < 0) {
            throw new DomainException('Modal awal tidak boleh negatif.');
        }

        try {
            return DB::transaction(function () use ($user, $openingCash): CashShift {
                if ($this->current($user) !== null) {
                    throw new DomainException('Anda masih punya shift yang terbuka.');
                }

                $shift = CashShift::query()->create([
                    'user_id' => $user->id,
                    'opened_at' => now(),
                    'opening_cash' => $openingCash,
                    'status' => CashShift::OPEN,
                ]);

                activity('shift-kas')->causedBy($user)->performedOn($shift)
                    ->withProperties(['modal_awal' => $openingCash])->log('Shift kas dibuka');

                return $shift;
            });
        } catch (QueryException) {
            throw new DomainException('Anda masih punya shift yang terbuka.'); // balapan dua klik: indeks unik menolak yang kedua
        }
    }

    public function close(CashShift $shift, User $by, int $countedCash, ?string $note = null): CashShift
    {
        if ($countedCash < 0) {
            throw new DomainException('Uang tunai tidak boleh negatif.');
        }

        return DB::transaction(function () use ($shift, $by, $countedCash, $note): CashShift {
            $locked = CashShift::query()->lockForUpdate()->findOrFail($shift->id);

            if ($locked->status !== CashShift::OPEN) {
                throw new DomainException('Shift ini sudah ditutup.');
            }

            if ($locked->user_id !== $by->id) {
                throw new DomainException('Shift hanya bisa ditutup oleh pemiliknya.');
            }

            $expected = $locked->opening_cash + $locked->cashIn();

            $locked->update([
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'difference' => $countedCash - $expected,
                'close_note' => filled($note) ? trim($note) : null,
                'status' => CashShift::CLOSED,
            ]);

            activity('shift-kas')->causedBy($by)->performedOn($locked)
                ->withProperties(['seharusnya' => $expected, 'dihitung' => $countedCash, 'selisih' => $countedCash - $expected])
                ->log('Shift kas ditutup');

            DB::afterCommit(fn () => PendingNotifier::shiftClosed($locked, $by));

            return $locked->refresh();
        });
    }

    public function review(CashShift $shift, User $by, ?string $note = null): CashShift
    {
        return DB::transaction(function () use ($shift, $by, $note): CashShift {
            $locked = CashShift::query()->lockForUpdate()->findOrFail($shift->id);

            if ($locked->status !== CashShift::CLOSED) {
                throw new DomainException('Shift ini tidak menunggu peninjauan.');
            }

            $locked->update([
                'status' => CashShift::REVIEWED,
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                'review_note' => filled($note) ? trim($note) : null,
            ]);

            activity('shift-kas')->causedBy($by)->performedOn($locked)
                ->withProperties(['selisih' => $locked->difference, 'catatan' => $locked->review_note])
                ->log('Shift kas ditinjau & diterima bendahara');

            return $locked->refresh();
        });
    }
}
