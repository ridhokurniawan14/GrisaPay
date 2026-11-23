<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'default_value' => 'integer'];
    }
}
