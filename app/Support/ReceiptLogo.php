<?php

namespace App\Support;

use App\Models\InstitutionSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Logo sekolah versi hitam-putih murni (1 bit) untuk struk printer dot-matrix/termal.
 * Hasil disimpan di cache; bila GD tidak ada atau logo kosong, struk tetap tercetak tanpa logo.
 */
final class ReceiptLogo
{
    private const WIDTH_PX = 128;

    public static function dataUri(): ?string
    {
        try {
            if (! config('grisapay.receipt.logo', true) || ! function_exists('imagecreatefromstring')) {
                return null;
            }

            $path = InstitutionSetting::current()->logo_path;
            $disk = Storage::disk('public');

            if (! $path || ! $disk->exists($path)) {
                return null;
            }

            $key = 'receipt-logo:'.md5($path.'|'.$disk->lastModified($path).'|'.self::WIDTH_PX);

            return Cache::rememberForever($key, fn (): ?string => self::convert($disk->get($path)));
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function convert(string $binary): ?string
    {
        $src = @imagecreatefromstring($binary);

        if ($src === false) {
            return null; // mis. SVG: GD tidak bisa membaca
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $tw = self::WIDTH_PX;
        $th = max(1, (int) round($h * $tw / $w));

        // Letakkan di atas kanvas putih (transparan menjadi putih), lalu perkecil.
        $canvas = imagecreatetruecolor($tw, $th);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $tw, $th, $w, $h);

        // Skala abu-abu, rentangkan kontras, lalu ambang batas -> hitam/putih tegas.
        $gray = [];
        $min = 255;
        $max = 0;

        for ($y = 0; $y < $th; $y++) {
            for ($x = 0; $x < $tw; $x++) {
                $rgb = imagecolorat($canvas, $x, $y);
                $v = (int) round(0.299 * (($rgb >> 16) & 255) + 0.587 * (($rgb >> 8) & 255) + 0.114 * ($rgb & 255));
                $gray[$y][$x] = $v;
                $min = min($min, $v);
                $max = max($max, $v);
            }
        }

        $range = max(1, $max - $min);
        $out = imagecreate($tw, $th);
        $white = imagecolorallocate($out, 255, 255, 255);
        $black = imagecolorallocate($out, 0, 0, 0);
        imagefill($out, 0, 0, $white);

        for ($y = 0; $y < $th; $y++) {
            for ($x = 0; $x < $tw; $x++) {
                if ((($gray[$y][$x] - $min) / $range) < 0.62) {
                    imagesetpixel($out, $x, $y, $black);
                }
            }
        }

        ob_start();
        imagepng($out);
        $png = (string) ob_get_clean();

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
