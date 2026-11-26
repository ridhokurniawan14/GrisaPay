<?php

namespace App\Services;

use App\Models\CashBookOpening;
use App\Models\CashEntry;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Buku kas: kas (tunai) dan bank (transfer/QRIS).
 * Masuk = pembayaran siswa berstatus Sah + pemasukan lain + mutasi masuk. Keluar = pengeluaran + mutasi keluar.
 */
class CashBook
{
    /** @return list<string> metode pembayaran yang masuk ke akun tsb */
    public static function methods(string $account): array
    {
        return $account === 'kas' ? [Payment::CASH] : [Payment::TRANSFER, Payment::QRIS];
    }

    private function paymentsSum(string $account, Carbon $a, Carbon $b): int
    {
        return (int) Payment::query()->where('status', Payment::PAID)->whereIn('method', self::methods($account))
            ->whereBetween('paid_at', [$a->copy()->startOfDay(), $b->copy()->endOfDay()])->sum('amount');
    }

    private function entriesNet(string $account, Carbon $a, Carbon $b): int
    {
        $q = CashEntry::query()->where('status', CashEntry::SAH)->where('account', $account)
            ->whereBetween('entry_date', [$a->toDateString(), $b->toDateString()]);

        return (int) (clone $q)->where('direction', 'in')->sum('amount') - (int) (clone $q)->where('direction', 'out')->sum('amount');
    }

    /**
     * @return array{account: string, label: string, tracked: bool, as_of: ?string, opening: int, rows: list<array<string, mixed>>, total_in: int, total_out: int, closing: int}
     */
    public function ledger(string $account, Carbon $from, Carbon $until): array
    {
        $from = $from->copy()->startOfDay();
        $until = $until->copy()->endOfDay();
        $open = CashBookOpening::query()->where('account', $account)->first();
        $tracked = $open !== null && $from->toDateString() >= $open->as_of->toDateString();
        $start = 0;

        if ($tracked) {
            $asOf = $open->as_of->copy()->startOfDay();
            $start = $open->amount;

            if ($from->gt($asOf)) {
                $prev = $from->copy()->subDay();
                $start += $this->paymentsSum($account, $asOf, $prev) + $this->entriesNet($account, $asOf, $prev);
            }
        }

        // Penerimaan pembayaran siswa, dijumlah per hari.
        $events = [];
        $pay = Payment::query()->where('status', Payment::PAID)->whereIn('method', self::methods($account))
            ->whereBetween('paid_at', [$from, $until])->get(['paid_at', 'amount']);

        foreach ($pay->groupBy(fn (Payment $p): string => $p->paid_at->format('Y-m-d')) as $date => $set) {
            $events[] = ['date' => $date, 'order' => 0, 'desc' => 'Penerimaan pembayaran siswa ('.$set->count().' transaksi)', 'ref' => '', 'in' => (int) $set->sum('amount'), 'out' => 0, 'entry_id' => null, 'kind' => 'pembayaran'];
        }

        $entries = CashEntry::query()->with('category')->where('status', CashEntry::SAH)->where('account', $account)
            ->whereBetween('entry_date', [$from->toDateString(), $until->toDateString()])->orderBy('entry_date')->orderBy('id')->get();

        foreach ($entries as $e) {
            $label = $e->kind === CashEntry::KELUAR ? ($e->category ? $e->category->name.' — ' : '').$e->description : $e->description;
            $events[] = [
                'date' => $e->entry_date->toDateString(), 'order' => 1 + $e->id / 1e9,
                'desc' => $label.($e->party ? ' ('.$e->party.')' : ''), 'ref' => (string) $e->reference_no,
                'in' => $e->direction === 'in' ? $e->amount : 0, 'out' => $e->direction === 'out' ? $e->amount : 0,
                'entry_id' => $e->id, 'kind' => $e->kind,
            ];
        }

        usort($events, fn ($a, $b) => [$a['date'], $a['order']] <=> [$b['date'], $b['order']]);

        $bal = $start;
        $in = $out = 0;

        foreach ($events as &$ev) {
            $bal += $ev['in'] - $ev['out'];
            $in += $ev['in'];
            $out += $ev['out'];
            $ev['balance'] = $bal;
        }
        unset($ev);

        return [
            'account' => $account, 'label' => CashEntry::ACCOUNTS[$account], 'tracked' => $tracked,
            'as_of' => $open?->as_of->toDateString(), 'opening' => $start, 'rows' => $events,
            'total_in' => $in, 'total_out' => $out, 'closing' => $bal,
        ];
    }
}
