<?php

namespace App\Support;

use App\Models\InstitutionSetting;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Identitas lembaga untuk kop laporan (nama, alamat, logo, warna tema, bendahara). Tahan terhadap data kosong. */
final class ReportBrand
{
    /** @return array{name: string, address: string, phone: string, logo: ?string, color: string, treasurer_name: string, treasurer_nip: string, city: string} */
    public static function get(): array
    {
        try {
            $s = InstitutionSetting::current();
        } catch (Throwable) {
            $s = null;
        }

        $color = ThemeColor::sanitize($s?->primary_color);

        return [
            'name' => (string) ($s?->name ?: $s?->short_name ?: 'GrisaPay'),
            'address' => trim(implode(', ', array_filter([(string) $s?->address, (string) $s?->city]))),
            'phone' => (string) ($s?->phone ?? ''),
            'logo' => self::logo($s?->logo_path),
            'color' => $color,
            'treasurer_name' => (string) ($s?->treasurer_name ?? ''),
            'treasurer_nip' => (string) ($s?->treasurer_nip ?? ''),
            'city' => (string) ($s?->city ?? ''),
        ];
    }

    /** Hitam atau putih, mana yang terbaca di atas warna latar ini. */
    public static function textOn(string $hex): string
    {
        $hex = ltrim($hex, '#');
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#111827' : '#FFFFFF';
    }

    private static function logo(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($path)) {
                return null;
            }

            return 'data:'.$disk->mimeType($path).';base64,'.base64_encode($disk->get($path));
        } catch (Throwable) {
            return null;
        }
    }
}
