<?php

namespace App\Support;

use App\Models\FeeCategory;
use App\Models\FeeComponent;

/** Pencatatan log aktivitas untuk perubahan tarif (eksplisit, sama pola dengan SettingsAudit). */
final class TariffAudit
{
    public static function created(FeeComponent $c): void
    {
        self::note('Tarif ditambah', ['perubahan' => [self::describe($c)]]);
    }

    /** @param array<string, mixed> $data nilai baru dari form (belum tersimpan) */
    public static function updated(FeeComponent $c, array $data): void
    {
        $labels = ['grade' => 'Tingkat', 'name' => 'Rincian', 'amount' => 'Nominal', 'fee_category_id' => 'Kategori', 'academic_year_id' => 'Tahun ajaran', 'sort_order' => 'Urutan'];
        $lines = [];

        foreach ($labels as $key => $label) {
            if (! array_key_exists($key, $data) || (string) $data[$key] === (string) $c->{$key}) {
                continue;
            }

            $lines[] = "{$label}: {$c->{$key}} → {$data[$key]}";
        }

        if ($lines !== []) {
            self::note('Tarif diubah: ' . $c->name, ['perubahan' => $lines]);
        }
    }

    public static function deleted(FeeComponent $c): void
    {
        self::note('Tarif dihapus', ['perubahan' => [self::describe($c)]]);
    }

    /** @param array<string, mixed> $properties */
    public static function note(string $message, array $properties = []): void
    {
        activity('Tarif')->causedBy(auth()->user())->withProperties($properties)->log($message);
    }

    private static function describe(FeeComponent $c): string
    {
        $category = FeeCategory::query()->whereKey($c->fee_category_id)->value('receipt_label');

        return "Kelas {$c->grade} · {$category} · {$c->name} · Rp " . number_format((int) $c->amount, 0, ',', '.');
    }
}
