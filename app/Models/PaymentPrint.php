<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentPrint extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Riwayat cetak tidak boleh dihapus.');
        });
    }

    protected function casts(): array
    {
        return ['copy_no' => 'integer', 'printed_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function label(): string
    {
        return $this->copy_no === 0 ? 'Cetak asli' : "Cetak ulang ke-{$this->copy_no}";
    }
}
