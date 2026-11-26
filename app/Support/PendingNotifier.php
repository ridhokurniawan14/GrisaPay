<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Filament\Resources\Discounts\DiscountResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\CashShift;
use App\Models\Discount;
use App\Models\Payment;
use App\Models\PaymentVoid;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Notifikasi lonceng (pojok kanan atas) untuk hal yang menunggu keputusan.
 * Dipanggil SETELAH transaksi database selesai dan tidak pernah menggagalkan proses utama.
 */
final class PendingNotifier
{
    public static function paymentPending(Payment $payment, ?User $actor): void
    {
        $payment->loadMissing('student');

        self::send(
            PermissionName::PaymentsVerify,
            $actor,
            'Pembayaran menunggu verifikasi',
            "{$payment->student->name} · Rp ".number_format($payment->amount, 0, ',', '.').' · '.$payment->methodText(),
            PaymentResource::getUrl('index').'?activeTab=menunggu',
            'heroicon-o-banknotes',
            'payment',
            (string) $payment->id,
        );
    }

    public static function voidRequested(Payment $payment, ?User $actor): void
    {
        $payment->loadMissing('student');

        self::send(
            PermissionName::PaymentsVoidApprove,
            $actor,
            'Pengajuan void menunggu persetujuan',
            "{$payment->student->name} · {$payment->receipt_no} · Rp ".number_format($payment->amount, 0, ',', '.'),
            PaymentResource::getUrl('index').'?activeTab=void',
            'heroicon-o-arrow-uturn-left',
            'void',
            (string) $payment->id,
        );
    }

    public static function discountsRequested(int $count, string $typeName, ?User $actor): void
    {
        self::send(
            PermissionName::DiscountsApprove,
            $actor,
            'Diskon menunggu persetujuan',
            "{$count} pengajuan · {$typeName}",
            DiscountResource::getUrl('index').'?activeTab=menunggu',
            'heroicon-o-receipt-percent',
            'discount',
            'all',
        );
    }

    public static function shiftClosed(CashShift $shift, User $by): void
    {
        $diff = (int) $shift->difference;
        $note = $diff === 0 ? 'Kas cocok.' : 'Selisih Rp '.number_format(abs($diff), 0, ',', '.').($diff < 0 ? ' (kurang)' : ' (lebih)').'.';

        self::send(
            PermissionName::CashSessionsReview,
            $by,
            'Shift kas menunggu ditinjau',
            "{$by->name} menutup shift. {$note}",
            \App\Filament\Resources\CashShifts\CashShiftResource::getUrl('index').'?activeTab=perlu',
            'heroicon-o-banknotes',
            'shift',
            (string) $shift->id,
        );
    }

    /**
     * Hapus notifikasi yang urusannya sudah selesai (sudah diverifikasi/ditolak/diputuskan),
     * supaya lonceng hanya berisi yang benar-benar masih menunggu.
     */
    public static function prune(): void
    {
        try {
            $rows = DatabaseNotification::query()
                ->whereIn('data->viewData->kind', ['payment', 'void', 'discount', 'shift'])
                ->get(['id', 'data']);

            if ($rows->isEmpty()) {
                return;
            }

            $pendingPayments = Payment::query()->where('status', Payment::PENDING)->pluck('id')->map(fn ($i): string => (string) $i)->all();
            $pendingVoids = PaymentVoid::query()->where('status', PaymentVoid::REQUESTED)->pluck('payment_id')->map(fn ($i): string => (string) $i)->all();
            $anyDiscount = Discount::query()->where('status', Discount::REQUESTED)->exists();
            $closedShifts = CashShift::query()->where('status', CashShift::CLOSED)->pluck('id')->map(fn ($i): string => (string) $i)->all();

            $stale = $rows->filter(function ($n) use ($pendingPayments, $pendingVoids, $anyDiscount, $closedShifts): bool {
                $kind = $n->data['viewData']['kind'] ?? null;
                $ref = (string) ($n->data['viewData']['ref'] ?? '');

                return match ($kind) {
                    'payment' => ! in_array($ref, $pendingPayments, true),
                    'void' => ! in_array($ref, $pendingVoids, true),
                    'discount' => ! $anyDiscount,
                    'shift' => ! in_array($ref, $closedShifts, true),
                    default => false,
                };
            })->pluck('id');

            if ($stale->isNotEmpty()) {
                DatabaseNotification::query()->whereIn('id', $stale)->delete();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Kirim ulang notifikasi untuk yang sudah menunggu sebelum fitur lonceng ada. */
    public static function backfill(): int
    {
        $sent = 0;

        foreach (Payment::query()->with('student')->where('status', Payment::PENDING)->get() as $payment) {
            if (! self::exists('payment', (string) $payment->id)) {
                self::paymentPending($payment, null);
                $sent++;
            }
        }

        foreach (PaymentVoid::query()->with('payment.student')->where('status', PaymentVoid::REQUESTED)->get() as $void) {
            if ($void->payment && ! self::exists('void', (string) $void->payment_id)) {
                self::voidRequested($void->payment, null);
                $sent++;
            }
        }

        $discounts = Discount::query()->where('status', Discount::REQUESTED)->count();

        if ($discounts > 0 && ! self::exists('discount', 'all')) {
            self::discountsRequested($discounts, 'beberapa jenis', null);
            $sent++;
        }

        return $sent;
    }

    private static function exists(string $kind, string $ref): bool
    {
        return DatabaseNotification::query()
            ->where('data->viewData->kind', $kind)
            ->where('data->viewData->ref', $ref)
            ->exists();
    }

    private static function send(PermissionName $permission, ?User $actor, string $title, string $body, string $url, string $icon, string $kind, string $ref): void
    {
        try {
            $recipients = self::recipients($permission, $actor);

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::make()
                ->title($title)
                ->body($body)
                ->icon($icon)
                ->iconColor('warning')
                ->viewData(['kind' => $kind, 'ref' => $ref])
                ->actions([Action::make('open')->label('Buka')->url($url)->markAsRead()])
                ->sendToDatabase($recipients);
        } catch (Throwable $e) {
            report($e); // mis. tabel notifikasi belum dibuat: jangan ganggu pembayaran/diskon
        }
    }

    /** @return Collection<int, User> */
    public static function recipients(PermissionName $permission, ?User $actor = null): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u): bool => $u->can($permission->value))
            ->values();
    }
}
