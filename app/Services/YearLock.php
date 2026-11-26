<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Discount;
use App\Models\FeeComponent;
use App\Models\YearClosing;
use DomainException;

/**
 * Penjaga tahun ajaran yang sudah ditutup buku.
 * Dikunci: tarif, pembuatan tagihan, item tagihan baru/hapus, pengajuan diskon baru.
 * TIDAK dikunci: pembayaran, verifikasi, void (tunggakan lama tetap bisa dibayar).
 * Dipasang lewat model event: YearLock::register() di AppServiceProvider::boot().
 */
final class YearLock
{
    /** @var array<int, bool>|null */
    private static ?array $cache = null;

    public static function isClosed(?int $yearId): bool
    {
        if ($yearId === null) {
            return false;
        }

        self::$cache ??= YearClosing::query()->whereNotNull('closed_at')->pluck('academic_year_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();

        return self::$cache[$yearId] ?? false;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function assertOpen(?int $yearId, string $what): void
    {
        if (self::isClosed($yearId)) {
            throw new DomainException("Tahun ajaran ini sudah ditutup buku, {$what} tidak bisa diubah. Buka kembali tutup buku di Pengaturan jika memang perlu.");
        }
    }

    public static function register(): void
    {
        FeeComponent::saving(fn (FeeComponent $m) => self::assertOpen((int) $m->academic_year_id, 'tarif'));
        FeeComponent::deleting(fn (FeeComponent $m) => self::assertOpen((int) $m->academic_year_id, 'tarif'));

        Bill::creating(fn (Bill $m) => self::assertOpen((int) $m->academic_year_id, 'tagihan'));

        BillItem::creating(fn (BillItem $m) => self::assertOpen(Bill::query()->whereKey($m->bill_id)->value('academic_year_id'), 'tagihan'));
        BillItem::deleting(fn (BillItem $m) => self::assertOpen(Bill::query()->whereKey($m->bill_id)->value('academic_year_id'), 'tagihan'));

        Discount::creating(fn (Discount $m) => self::assertOpen(Bill::query()->whereKey($m->bill_id)->value('academic_year_id'), 'diskon'));
    }
}
