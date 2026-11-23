<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Salinan kelas dari GrisaID (hanya-baca; diisi oleh sinkronisasi). */
class Classroom extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['grade' => 'integer', 'group_no' => 'integer'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
}
