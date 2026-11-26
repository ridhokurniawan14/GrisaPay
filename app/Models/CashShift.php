<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashShift extends Model
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const REVIEWED = 'reviewed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'opening_cash' => 'integer',
            'expected_cash' => 'integer',
            'counted_cash' => 'integer',
            'difference' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Tunai yang sah selama shift (pembayaran yang di-void tidak dihitung). */
    public function cashIn(): int
    {
        return (int) $this->payments()->where('method', Payment::CASH)->where('status', Payment::PAID)->sum('amount');
    }

    /** Uang tunai yang seharusnya ada di laci: snapshot bila sudah ditutup, hitung langsung bila masih terbuka. */
    public function expectedCash(): int
    {
        return $this->expected_cash ?? ($this->opening_cash + $this->cashIn());
    }
}
