<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Salinan tahun ajaran dari GrisaID (hanya-baca; diisi oleh sinkronisasi). */
class AcademicYear extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }
}
