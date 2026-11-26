<?php

namespace App\Services;

use App\Models\CashBookOpening;
use App\Models\CashEntry;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Pencatatan buku kas. Catatan tidak diedit/dihapus: koreksi dilakukan dengan membatalkan lalu mencatat ulang. */
class CashEntryService
{
    private function amount(mixed $v): int
    {
        $n = (int) preg_replace('/\D+/', '', (string) $v);

        if ($n <= 0) {
            throw new DomainException('Jumlah harus lebih dari 0.');
        }

        return $n;
    }

    private function account(string $a): string
    {
        return array_key_exists($a, CashEntry::ACCOUNTS) ? $a : throw new DomainException('Akun tidak dikenal.');
    }

    private function date(mixed $d): string
    {
        $date = \Illuminate\Support\Carbon::parse($d, config('app.timezone'))->startOfDay();

        if ($date->gt(now(config('app.timezone'))->endOfDay())) {
            throw new DomainException('Tanggal tidak boleh di masa depan.');
        }

        return $date->toDateString();
    }

    /** @param array<string, mixed> $d */
    public function expense(array $d, User $by): CashEntry
    {
        $e = CashEntry::create([
            'entry_date' => $this->date($d['entry_date']),
            'kind' => CashEntry::KELUAR,
            'account' => $this->account((string) $d['account']),
            'direction' => 'out',
            'amount' => $this->amount($d['amount']),
            'expense_category_id' => $d['expense_category_id'] ?? throw new DomainException('Kategori wajib diisi.'),
            'description' => trim((string) $d['description']),
            'party' => filled($d['party'] ?? null) ? trim((string) $d['party']) : null,
            'reference_no' => filled($d['reference_no'] ?? null) ? trim((string) $d['reference_no']) : null,
            'proof_path' => $this->file($d['proof'] ?? null),
            'created_by' => $by->id,
        ]);

        $this->log('Mencatat pengeluaran', $e, $by);

        return $e;
    }

    /** Pemasukan di luar pembayaran siswa (BOS, donasi, bunga bank, dll.). @param array<string, mixed> $d */
    public function income(array $d, User $by): CashEntry
    {
        $e = CashEntry::create([
            'entry_date' => $this->date($d['entry_date']),
            'kind' => CashEntry::MASUK,
            'account' => $this->account((string) $d['account']),
            'direction' => 'in',
            'amount' => $this->amount($d['amount']),
            'description' => trim((string) $d['description']),
            'party' => filled($d['party'] ?? null) ? trim((string) $d['party']) : null,
            'reference_no' => filled($d['reference_no'] ?? null) ? trim((string) $d['reference_no']) : null,
            'proof_path' => $this->file($d['proof'] ?? null),
            'created_by' => $by->id,
        ]);

        $this->log('Mencatat pemasukan lain', $e, $by);

        return $e;
    }

    /** Setor tunai ke bank (kas → bank) atau tarik tunai (bank → kas): dua baris berpasangan. */
    public function transfer(string $from, int|string $amount, mixed $date, ?string $note, User $by): void
    {
        $from = $this->account($from);
        $to = $from === 'kas' ? 'bank' : 'kas';
        $amount = $this->amount($amount);
        $date = $this->date($date);
        $group = (string) Str::uuid();
        $text = $from === 'kas' ? 'Setor tunai ke bank' : 'Tarik tunai dari bank';
        $desc = trim($text.($note ? ' — '.$note : ''));

        DB::transaction(function () use ($from, $to, $amount, $date, $group, $desc, $by): void {
            foreach ([[$from, 'out'], [$to, 'in']] as [$account, $dir]) {
                $e = CashEntry::create([
                    'entry_date' => $date, 'kind' => CashEntry::MUTASI, 'account' => $account, 'direction' => $dir,
                    'amount' => $amount, 'description' => $desc, 'transfer_group' => $group, 'created_by' => $by->id,
                ]);

                if ($dir === 'out') {
                    $this->log('Mencatat mutasi kas/bank', $e, $by);
                }
            }
        });
    }

    public function void(CashEntry $entry, User $by, string $reason): void
    {
        DB::transaction(function () use ($entry, $by, $reason): void {
            $rows = CashEntry::query()
                ->when($entry->transfer_group, fn ($q) => $q->where('transfer_group', $entry->transfer_group), fn ($q) => $q->where('id', $entry->id))
                ->lockForUpdate()->get();

            if ($rows->contains(fn (CashEntry $r): bool => $r->status !== CashEntry::SAH)) {
                throw new DomainException('Catatan ini sudah dibatalkan.');
            }

            foreach ($rows as $r) {
                $r->update(['status' => CashEntry::BATAL, 'voided_by' => $by->id, 'voided_at' => now(), 'void_reason' => trim($reason)]);
            }

            $this->log('Membatalkan catatan kas', $entry, $by, ['alasan' => $reason]);
        });
    }

    /** Saldo awal = saldo pada AWAL tanggal as_of. Hanya pergerakan sejak tanggal itu yang dihitung. */
    public function setOpening(string $account, int|string $amount, mixed $asOf, User $by): CashBookOpening
    {
        $account = $this->account($account);
        $amount = (int) preg_replace('/[^\d-]+/', '', (string) $amount);

        $o = CashBookOpening::updateOrCreate(['account' => $account], [
            'amount' => $amount,
            'as_of' => \Illuminate\Support\Carbon::parse($asOf, config('app.timezone'))->toDateString(),
            'updated_by' => $by->id,
        ]);

        activity('buku-kas')->causedBy($by)->withProperties(['akun' => $account, 'saldo' => $amount, 'per_tanggal' => $o->as_of->toDateString()])->log('Mengatur saldo awal buku kas');

        return $o;
    }

    private function file(mixed $f): ?string
    {
        $f = is_array($f) ? reset($f) : $f;

        return filled($f) ? (string) $f : null;
    }

    /** @param array<string, mixed> $extra */
    private function log(string $msg, CashEntry $e, User $by, array $extra = []): void
    {
        activity('buku-kas')->causedBy($by)->performedOn($e)
            ->withProperties($extra + ['jenis' => $e->kind, 'akun' => $e->account, 'jumlah' => $e->amount, 'tanggal' => $e->entry_date->toDateString()])
            ->log($msg);
    }
}
