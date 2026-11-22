<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/** Pencatatan eksplisit perubahan Pengaturan Lembaga (nilai lama -> baru, label Indonesia). */
final class SettingsAudit
{
    /**
     * Baris perubahan antara dua kondisi pengaturan. Hanya kolom yang benar-benar berubah.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    public static function lines(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $value) {
            $was = $before[$key] ?? null;

            if (self::normalize($was) === self::normalize($value)) {
                continue;
            }

            $old[$key] = $was;
            $new[$key] = $value;
        }

        return ActivityChangeFormatter::lines(['attributes' => $new, 'old' => $old]);
    }

    /** @param list<string> $lines */
    public static function log(Model $setting, array $lines, bool $isNew): void
    {
        if ($lines === []) {
            return;
        }

        activity('institution-setting')
            ->performedOn($setting)
            ->causedBy(auth()->user())
            ->withProperties(['perubahan' => $lines])
            ->log($isNew ? 'created' : 'updated');
    }

    private static function normalize(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
