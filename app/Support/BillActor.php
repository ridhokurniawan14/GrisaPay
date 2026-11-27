<?php

namespace App\Support;

/** Siapa yang memanggil API tagihan: staf GrisaPay, siswa, atau guru (wali kelas). */
final class BillActor
{
    /** @param list<string> $classroomUuids ID kelas (GrisaID) yang diwalikan guru */
    public function __construct(
        public readonly string $type,                 // staff | student | teacher
        public readonly ?string $studentUuid = null,  // ID siswa di GrisaID
        public readonly array $classroomUuids = [],
        public readonly ?string $name = null,
    ) {
    }

    public function isStaff(): bool
    {
        return $this->type === 'staff';
    }
}
