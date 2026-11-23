<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Salinan siswa dari GrisaID (hanya-baca; diisi oleh sinkronisasi). */
class Student extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'entry_year' => 'integer',
            'grisaid_updated_at' => 'datetime',
            'grisaid_deleted_at' => 'datetime',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }
}
