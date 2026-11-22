<?php

namespace App\Support;

/**
 * Keputusan menghapus berkas unggahan lama dari disk setelah diganti atau dihapus.
 * Hanya berkas di dalam folder "institution/" yang boleh dihapus, supaya nilai yang salah di database
 * tidak pernah bisa menghapus berkas lain.
 */
final class StaleUpload
{
    public const DIRECTORY = 'institution/';

    public static function shouldDelete(mixed $old, mixed $new): bool
    {
        if (! is_string($old) || $old === '' || $old === $new) {
            return false;
        }

        return str_starts_with($old, self::DIRECTORY)
            && ! str_contains($old, '..')
            && ! str_contains($old, '\\');
    }
}
