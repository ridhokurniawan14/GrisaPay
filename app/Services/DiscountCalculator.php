<?php

namespace App\Services;

/** Hitungan murni (tanpa database): membagi diskon ke item-item yang menjadi sasaran. */
final class DiscountCalculator
{
    /**
     * @param  array<int, int>  $bases  id item => sisa yang masih bisa dipotong (tagihan - diskon lain - sudah dibayar), berurutan
     * @return array<int, int> id item => potongan (> 0 saja)
     */
    public static function allocate(array $bases, string $mode, int $value): array
    {
        $result = [];

        if ($value <= 0) {
            return $result;
        }

        if ($mode === 'percent') {
            $value = min($value, 100);

            foreach ($bases as $id => $base) {
                $cut = intdiv(max(0, $base) * $value, 100);

                if ($cut > 0) {
                    $result[$id] = $cut;
                }
            }

            return $result;
        }

        $left = $value;

        foreach ($bases as $id => $base) {
            if ($left <= 0) {
                break;
            }

            $cut = min(max(0, $base), $left);

            if ($cut > 0) {
                $result[$id] = $cut;
                $left -= $cut;
            }
        }

        return $result;
    }
}
