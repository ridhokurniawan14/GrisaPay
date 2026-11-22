<?php

namespace App\Support;

use App\Enums\PermissionName;

/**
 * Jembatan antara form Peran (satu daftar centang per kelompok) dan daftar izin milik peran.
 * Tiap kelompok punya satu kolom form; kolom-kolom itu digabung kembali saat disimpan.
 */
final class PermissionGroups
{
    /** Nama kolom form untuk satu kelompok, mis. "Tarif & tagihan" => "perm_tarif_tagihan". */
    public static function fieldName(string $group): string
    {
        return 'perm_' . trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($group)), '_');
    }

    /** @return list<string> semua nama kolom kelompok */
    public static function fieldNames(): array
    {
        return array_map(self::fieldName(...), array_keys(PermissionName::grouped()));
    }

    /** @return list<string> semua nama izin di katalog */
    public static function catalog(): array
    {
        return array_map(fn(PermissionName $case): string => $case->value, PermissionName::cases());
    }

    /**
     * Isi awal form dari izin yang sudah dimiliki peran.
     *
     * @param  list<string>  $granted
     * @return array<string, list<string>> [nama kolom => izin yang dipilih di kelompok itu]
     */
    public static function stateFromGranted(array $granted): array
    {
        $state = [];

        foreach (PermissionName::grouped() as $group => $cases) {
            $names = array_map(fn(PermissionName $case): string => $case->value, $cases);
            $state[self::fieldName($group)] = array_values(array_intersect($names, $granted));
        }

        return $state;
    }

    /**
     * Pisahkan pilihan izin dari data form.
     * Mengembalikan [izin terpilih atau null, sisa data]. NULL berarti form izin tidak ikut terkirim
     * (mis. peran Super Admin yang daftarnya disembunyikan), sehingga izin TIDAK boleh diubah.
     * Hanya nama izin yang ada di katalog yang diterima.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>|null, 1: array<string, mixed>}
     */
    public static function extract(array $data): array
    {
        $fields = self::fieldNames();
        $present = array_intersect($fields, array_keys($data));

        $names = [];

        foreach ($present as $field) {
            $value = $data[$field] ?? [];
            $names = array_merge($names, is_array($value) ? array_map('strval', $value) : []);
        }

        $rest = array_diff_key($data, array_flip($fields));

        if ($present === []) {
            return [null, $rest];
        }

        return [array_values(array_unique(array_intersect($names, self::catalog()))), $rest];
    }
}
