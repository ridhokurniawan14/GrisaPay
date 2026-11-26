<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArrearImport extends Model
{
    public const ACTIVE = 'aktif';

    public const UNDONE = 'dibatalkan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_bill_ids' => 'array', 'total_amount' => 'integer', 'undone_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
