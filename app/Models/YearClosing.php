<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YearClosing extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime', 'reopened_at' => 'datetime', 'snapshot' => 'array'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
