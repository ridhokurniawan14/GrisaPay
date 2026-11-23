<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentAllocation extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Alokasi pembayaran tidak boleh dihapus. Gunakan void.');
        });
    }

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function billItem(): BelongsTo
    {
        return $this->belongsTo(BillItem::class);
    }
}
