<?php

namespace App\Enums;

/** Peran bawaan sistem. Tidak bisa dihapus atau diganti namanya; hak aksesnya diatur dari halaman Peran. */
enum RoleName: string
{
    case SuperAdmin = 'superadmin';
    case Treasurer = 'bendahara';
    case Cashier = 'kasir';
    case Principal = 'kepala_sekolah';
    case HomeroomTeacher = 'wali_kelas';
    case Student = 'siswa';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Treasurer => 'Bendahara',
            self::Cashier => 'Kasir',
            self::Principal => 'Kepala Sekolah',
            self::HomeroomTeacher => 'Wali Kelas',
            self::Student => 'Siswa',
        };
    }
}
