<?php

namespace App\Support;

/**
 * Menyusun tampilan log aktivitas: ringkasan singkat untuk tabel dan rincian lengkap untuk panel Detail.
 * Bekerja dari kolom attribute_changes dan properties milik Spatie Activitylog v5.
 */
final class ActivityDetail
{
    public const CHANGES_LABEL = 'Perubahan data';

    private const LABELS = [
        'peran' => 'Peran',
        'peran_ditambah' => 'Peran ditambah',
        'peran_dicabut' => 'Peran dicabut',
        'hak_ditambah' => 'Hak akses ditambah',
        'hak_dicabut' => 'Hak akses dicabut',
        'ip' => 'Alamat IP',
        'perangkat' => 'Perangkat',
        'email' => 'Email yang dicoba',
        'dihapus' => 'Log dihapus',
        'disisakan' => 'Log disisakan',
    ];

    /** @return list<array{label: string, items: list<string>}> */
    public static function sections(mixed $attributeChanges, mixed $properties): array
    {
        $props = self::toArray($properties);
        $lines = ActivityChangeFormatter::lines($attributeChanges);

        // Pencatatan eksplisit (mis. Pengaturan Lembaga) menyimpan baris perubahan di properties.
        if (isset($props['perubahan']) && is_array($props['perubahan'])) {
            $lines = array_merge($lines, array_map('strval', array_values($props['perubahan'])));
            unset($props['perubahan']);
        }

        $sections = [];

        if ($lines !== []) {
            $sections[] = ['label' => self::CHANGES_LABEL, 'items' => $lines];
        }

        foreach ($props as $key => $value) {
            $items = is_array($value)
                ? array_map(fn($item): string => is_scalar($item) ? (string) $item : (string) json_encode($item, JSON_UNESCAPED_UNICODE), array_values($value))
                : [is_bool($value) ? ($value ? 'ya' : 'tidak') : (string) $value];

            $items = array_values(array_filter($items, fn(string $item): bool => $item !== ''));

            if ($items !== []) {
                $sections[] = ['label' => self::LABELS[$key] ?? (string) $key, 'items' => $items];
            }
        }

        return $sections;
    }

    /**
     * Ringkasan untuk kolom tabel: baris perubahan data apa adanya (dipotong), daftar panjang menjadi "N item".
     *
     * @return list<string>
     */
    public static function summary(mixed $attributeChanges, mixed $properties): array
    {
        $lines = [];

        foreach (self::sections($attributeChanges, $properties) as $section) {
            $items = $section['items'];

            if ($section['label'] === self::CHANGES_LABEL) {
                foreach ($items as $item) {
                    $lines[] = self::cut($item);
                }

                continue;
            }

            $lines[] = match (true) {
                count($items) === 1 => self::cut($section['label'] . ': ' . $items[0]),
                count($items) <= 3 => self::cut($section['label'] . ': ' . implode(', ', $items)),
                default => $section['label'] . ': ' . count($items) . ' item',
            };
        }

        return $lines;
    }

    private static function cut(string $text): string
    {
        return mb_strimwidth($text, 0, 90, '…');
    }

    /** @return array<string, mixed> */
    private static function toArray(mixed $value): array
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        }

        return is_array($value) ? $value : [];
    }
}
