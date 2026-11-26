<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashEntry extends Model
{
    public const KELUAR = 'keluar';

    public const MASUK = 'masuk';

    public const MUTASI = 'mutasi';

    public const SAH = 'sah';

    public const BATAL = 'batal';

    public const ACCOUNTS = ['kas' => 'Kas (tunai)', 'bank' => 'Bank (transfer/QRIS)'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'amount' => 'integer', 'voided_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
