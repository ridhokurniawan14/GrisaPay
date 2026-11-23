<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    public const REQUESTED = 'diajukan';

    public const APPROVED = 'disetujui';

    public const REJECTED = 'ditolak';

    public const REVOKED = 'dicabut';

    public const SCOPE_BILL = 'bill';

    public const SCOPE_CATEGORY = 'category';

    public const SCOPE_ITEM = 'item';

    public const MODE_PERCENT = 'percent';

    public const MODE_AMOUNT = 'amount';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'granted_amount' => 'integer', 'decided_at' => 'datetime'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DiscountType::class, 'discount_type_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }

    public function billItem(): BelongsTo
    {
        return $this->belongsTo(BillItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DiscountAllocation::class);
    }

    public function scopeText(): string
    {
        return match ($this->scope) {
            self::SCOPE_CATEGORY => 'Kategori: '.($this->category?->receipt_label ?? '-'),
            self::SCOPE_ITEM => 'Rincian: '.($this->billItem?->name ?? '-'),
            default => 'Seluruh tagihan',
        };
    }

    public function valueText(): string
    {
        return $this->mode === self::MODE_PERCENT
            ? "{$this->value}%"
            : 'Rp '.number_format($this->value, 0, ',', '.');
    }
}
