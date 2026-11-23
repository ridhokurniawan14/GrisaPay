<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeCategory extends Model
{
    public const CARRYOVER = 'tunggakan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function components(): HasMany
    {
        return $this->hasMany(FeeComponent::class);
    }
}
