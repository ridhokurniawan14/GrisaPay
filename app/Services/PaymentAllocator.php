<?php

namespace App\Services;

/** Hitungan murni: membagi uang masuk ke item tagihan berurutan (sudah diurutkan tertua dulu oleh pemanggil). */
final class PaymentAllocator
{
    /**
     * @param  array<int, int>  $outstanding  id item => sisa yang harus dibayar, urut prioritas
     * @return array{allocations: array<int, int>, leftover: int} leftover > 0 berarti uang melebihi seluruh sisa tagihan
     */
    public static function allocate(array $outstanding, int $amount): array
    {
        $allocations = [];
        $left = max(0, $amount);

        foreach ($outstanding as $id => $remaining) {
            if ($left <= 0) {
                break;
            }

            $take = min(max(0, $remaining), $left);

            if ($take > 0) {
                $allocations[$id] = $take;
                $left -= $take;
            }
        }

        return ['allocations' => $allocations, 'leftover' => $left];
    }
}
