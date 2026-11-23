<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGuardian extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_primary_contact' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
