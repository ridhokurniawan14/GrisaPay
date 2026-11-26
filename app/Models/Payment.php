<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Payment extends Model
{
    public const PENDING = 'menunggu';

    public const PAID = 'sah';

    public const REJECTED = 'ditolak';

    public const VOID = 'void';

    public const CASH = 'cash';

    public const TRANSFER = 'transfer';

    public const QRIS = 'qris';

    public const METHODS = [self::CASH => 'Tunai', self::TRANSFER => 'Transfer', self::QRIS => 'QRIS'];

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Transaksi keuangan tidak pernah dihapus. Koreksi = void yang disetujui bendahara.
        static::deleting(function (): never {
            throw new LogicException('Pembayaran tidak boleh dihapus. Gunakan void.');
        });
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'datetime', 'verified_at' => 'datetime', 'receipt_snapshot' => 'array'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function prints(): HasMany
    {
        return $this->hasMany(PaymentPrint::class)->orderBy('copy_no');
    }

    public function voids(): HasMany
    {
        return $this->hasMany(PaymentVoid::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function pendingVoid(): ?PaymentVoid
    {
        return $this->voids->firstWhere('status', PaymentVoid::REQUESTED);
    }

    public function methodText(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }
}
