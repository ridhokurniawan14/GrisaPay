<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Models\Student;
use App\Models\User;

/**
 * Siapa boleh melihat tagihan siapa lewat API. Token HARUS milik pengguna (bukan client credentials).
 *  - staf (bills.view)        : semua siswa
 *  - siswa (portal.student)   : hanya dirinya  -> butuh tautan akun-siswa (users.student_id), BELUM ada
 *  - wali kelas (portal.homeroom): hanya kelasnya -> butuh modul wali kelas di GrisaID, BELUM ada
 * Selama dua tautan itu belum ada, siswa & wali kelas ditolak (aman: gagal tertutup).
 */
final class BillAccess
{
    public function all(User $user): bool
    {
        return $user->can(PermissionName::BillsView->value);
    }

    public function student(User $user, Student $student): bool
    {
        if ($this->all($user)) {
            return true;
        }

        return $user->can(PermissionName::PortalStudent->value)
            && isset($user->student_id) && (int) $user->student_id === (int) $student->id;
    }

    /** @param list<int> $classroomIds kelas yang diampu (diisi nanti dari modul wali kelas) */
    public function classroom(User $user, int $classroomId, array $classroomIds = []): bool
    {
        return $this->all($user)
            || ($user->can(PermissionName::PortalHomeroom->value) && in_array($classroomId, $classroomIds, true));
    }
}
