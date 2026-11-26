<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashBookOpening extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['as_of' => 'date', 'amount' => 'integer'];
    }
}
