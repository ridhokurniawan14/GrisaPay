<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\CashEntry;
use App\Models\CashShift;
use App\Models\Payment;
use App\Models\PaymentVoid;
use App\Services\CashBook;
use App\Services\CashRecap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Angka-angka dashboard keuangan. Hanya pembayaran berstatus Sah yang dihitung sebagai uang masuk. */
final class DashboardMetrics
{
    /** @var array<string, mixed> */
    private static array $memo = [];

    private static function once(string $key, callable $fn): mixed
    {
        return self::$memo[$key] ??= $fn();
    }

    /** Pembayaran + rekap pada satu periode (dipakai bersama oleh beberapa widget dalam satu permintaan). @return array{payments: Collection<int, Payment>, summary: array<string, mixed>} */
    public static function period(string $name): array
    {
        return self::once('period:'.$name, function () use ($name): array {
            [$a, $b] = match ($name) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
                'month' => [now()->startOfMonth(), now()->endOfDay()],
                'prev_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfDay()],
            };

            $payments = CashRecap::payments($a, $b);

            return ['payments' => $payments, 'summary' => CashRecap::summarize($payments)];
        });
    }

    /** @return array{count: int, total: int} */
    public static function voidRequests(): array
    {
        $q = PaymentVoid::query()->where('status', PaymentVoid::REQUESTED);

        return ['count' => (clone $q)->count(), 'total' => (int) Payment::query()->whereIn('id', (clone $q)->select('payment_id'))->sum('amount')];
    }

    /** @return array{labels: list<string>, totals: list<int>, cash: list<int>} */
    public static function dailySeries(int $days = 30): array
    {
        return self::once('daily:'.$days, function () use ($days): array {
            $start = now()->subDays($days - 1)->startOfDay();
            $rows = Payment::query()
                ->where('status', Payment::PAID)
                ->where('paid_at', '>=', $start)
                ->get(['paid_at', 'amount', 'method'])
                ->groupBy(fn (Payment $p): string => $p->paid_at->format('Y-m-d'));

            $labels = $totals = $cash = [];

            for ($i = 0; $i < $days; $i++) {
                $d = $start->copy()->addDays($i);
                $set = $rows->get($d->format('Y-m-d'), collect());
                $labels[] = $d->format('d/m');
                $totals[] = (int) $set->sum('amount');
                $cash[] = (int) $set->where('method', Payment::CASH)->sum('amount');
            }

            return ['labels' => $labels, 'totals' => $totals, 'cash' => $cash];
        });
    }

    /**
     * Sisa tagihan per tingkat.
     *
     * @return list<array{grade: int, label: string, due: int, paid: int, remaining: int}>
     */
    public static function outstandingByGrade(): array
    {
        return self::once('grade', function (): array {
            $due = DB::table('bill_items as bi')
                ->join('bills as b', 'b.id', '=', 'bi.bill_id')
                ->selectRaw('b.grade as grade, SUM(bi.amount - LEAST(bi.discount_amount, bi.amount)) as total')
                ->groupBy('b.grade')->pluck('total', 'grade');

            $paid = DB::table('payment_allocations as pa')
                ->join('payments as p', 'p.id', '=', 'pa.payment_id')
                ->join('bill_items as bi', 'bi.id', '=', 'pa.bill_item_id')
                ->join('bills as b', 'b.id', '=', 'bi.bill_id')
                ->where('p.status', Payment::PAID)
                ->selectRaw('b.grade as grade, SUM(pa.amount) as total')
                ->groupBy('b.grade')->pluck('total', 'grade');

            $bucket = fn ($g): int => in_array((int) $g, [10, 11, 12], true) ? (int) $g : 0;
            $agg = [];

            foreach ([$due->all(), $paid->all()] as $i => $map) {
                foreach ($map as $g => $v) {
                    $k = $bucket($g);
                    $agg[$k][$i] = ($agg[$k][$i] ?? 0) + (int) $v;
                }
            }

            $out = [];

            foreach (CashRecap::GRADE_LABELS as $g => $label) {
                $d = (int) ($agg[$g][0] ?? 0);
                $p = (int) ($agg[$g][1] ?? 0);

                if ($d !== 0 || $p !== 0) {
                    $out[] = ['grade' => $g, 'label' => $label, 'due' => $d, 'paid' => $p, 'remaining' => max(0, $d - $p)];
                }
            }

            return $out;
        });
    }

    /** Tagihan & pembayaran sah per siswa. @return Collection<int, array{student_id: int, due: int, paid: int, remaining: int}> */
    private static function perStudent(?int $yearId = null): Collection
    {
        return self::once('perStudent:'.($yearId ?? 'all'), function () use ($yearId): Collection {
            $due = DB::table('bill_items as bi')
                ->join('bills as b', 'b.id', '=', 'bi.bill_id')
                ->when($yearId, fn ($q) => $q->where('b.academic_year_id', $yearId))
                ->selectRaw('b.student_id as sid, SUM(bi.amount - LEAST(bi.discount_amount, bi.amount)) as total')
                ->groupBy('b.student_id')->pluck('total', 'sid');

            $paid = DB::table('payment_allocations as pa')
                ->join('payments as p', 'p.id', '=', 'pa.payment_id')
                ->join('bill_items as bi', 'bi.id', '=', 'pa.bill_item_id')
                ->join('bills as b', 'b.id', '=', 'bi.bill_id')
                ->where('p.status', Payment::PAID)
                ->when($yearId, fn ($q) => $q->where('b.academic_year_id', $yearId))
                ->selectRaw('b.student_id as sid, SUM(pa.amount) as total')
                ->groupBy('b.student_id')->pluck('total', 'sid');

            return $due->map(fn ($d, $sid): array => [
                'student_id' => (int) $sid,
                'due' => (int) $d,
                'paid' => (int) ($paid[$sid] ?? 0),
                'remaining' => max(0, (int) $d - (int) ($paid[$sid] ?? 0)),
            ])->values();
        });
    }

    /** Status pelunasan siswa pada tahun ajaran aktif. @return array{lunas: int, sebagian: int, belum: int, due: int, paid: int, year: ?string} */
    public static function studentStatus(): array
    {
        $year = AcademicYear::current();
        $rows = $year ? self::perStudent($year->id) : collect();

        return [
            'lunas' => $rows->filter(fn ($r): bool => $r['remaining'] === 0)->count(),
            'sebagian' => $rows->filter(fn ($r): bool => $r['remaining'] > 0 && $r['paid'] > 0)->count(),
            'belum' => $rows->filter(fn ($r): bool => $r['remaining'] > 0 && $r['paid'] === 0)->count(),
            'due' => (int) $rows->sum('due'),
            'paid' => (int) $rows->sum('paid'),
            'year' => $year?->code,
        ];
    }

    /** Siswa dengan sisa tagihan terbesar (semua tahun ajaran). @return list<array<string, mixed>> */
    public static function topArrears(int $limit = 10): array
    {
        $top = self::perStudent()->filter(fn ($r): bool => $r['remaining'] > 0)->sortByDesc('remaining')->take($limit)->values();

        if ($top->isEmpty()) {
            return [];
        }

        $ids = $top->pluck('student_id')->all();
        $students = \App\Models\Student::query()->whereIn('id', $ids)->get(['id', 'name', 'nisn', 'status'])->keyBy('id');
        $classes = DB::table('bills')->whereIn('student_id', $ids)->orderByDesc('academic_year_id')->get(['student_id', 'classroom_name', 'grade'])->unique('student_id')->keyBy('student_id');

        return $top->map(function (array $r) use ($students, $classes): array {
            $s = $students->get($r['student_id']);
            $c = $classes->get($r['student_id']);
            $g = (int) ($c->grade ?? 0);

            return $r + [
                'name' => $s?->name ?? '-',
                'nisn' => $s?->nisn ?? '-',
                'class' => in_array($g, [10, 11, 12], true) ? (string) ($c->classroom_name ?? '-') : 'Alumni / tanpa kelas',
                'student_status' => (string) ($s?->status ?? ''),
            ];
        })->all();
    }

    /** Penerimaan per kasir pada periode. @return list<array{name: string, count: int, cash: int, other: int, total: int, last: string}> */
    public static function byCashier(string $period): array
    {
        $sah = self::period($period)['payments']->where('status', Payment::PAID);

        return $sah->groupBy(fn (Payment $p): string => (string) ($p->creator?->name ?? '-'))
            ->map(fn (Collection $g, string $name): array => [
                'name' => $name,
                'count' => $g->count(),
                'cash' => (int) $g->where('method', Payment::CASH)->sum('amount'),
                'other' => (int) $g->where('method', '!=', Payment::CASH)->sum('amount'),
                'total' => (int) $g->sum('amount'),
                'last' => $g->max('paid_at')->format('H:i'),
            ])->sortByDesc('total')->values()->all();
    }

    /** @return array{rows: list<array<string, mixed>>, is_today: bool} */
    public static function latestPayments(int $limit = 10): array
    {
        $today = self::period('today')['payments'];
        $isToday = $today->isNotEmpty();
        $list = $isToday
            ? $today->sortByDesc('paid_at')->take($limit)->values()
            : Payment::query()->with(['student', 'creator', 'allocations.billItem.category'])->orderByDesc('paid_at')->limit($limit)->get();

        $place = CashRecap::placement($list);

        return [
            'is_today' => $isToday,
            'rows' => $list->map(fn (Payment $p): array => [
                'when' => $p->paid_at->format($isToday ? 'H:i' : 'd/m H:i'),
                'receipt' => $p->receipt_no ?? '-',
                'student' => $p->student?->name ?? '-',
                'class' => $place[$p->id]['class'] ?? '-',
                'method' => $p->methodText(),
                'amount' => (int) $p->amount,
                'status' => $p->status,
                'creator' => $p->creator?->name ?? '-',
                'for' => $p->allocations->map(fn ($a): string => (string) ($a->billItem?->category?->receipt_label ?? $a->billItem?->name ?? '-'))->unique()->implode(', '),
            ])->all(),
        ];
    }

    /** Pengeluaran sah pada rentang tanggal. @return array{count: int, total: int} */
    public static function expenses(Carbon $a, Carbon $b): array
    {
        return self::once('exp:'.$a->toDateString().$b->toDateString(), function () use ($a, $b): array {
            $q = CashEntry::query()->where('kind', CashEntry::KELUAR)->where('status', CashEntry::SAH)
                ->whereBetween('entry_date', [$a->toDateString(), $b->toDateString()]);

            return ['count' => (clone $q)->count(), 'total' => (int) (clone $q)->sum('amount')];
        });
    }

    /** Pemasukan di luar pembayaran siswa (BOS, donasi, dll.). */
    public static function otherIncome(Carbon $a, Carbon $b): int
    {
        return (int) CashEntry::query()->where('kind', CashEntry::MASUK)->where('status', CashEntry::SAH)
            ->whereBetween('entry_date', [$a->toDateString(), $b->toDateString()])->sum('amount');
    }

    /** @return list<array{name: string, count: int, total: int}> */
    public static function expensesByCategory(Carbon $a, Carbon $b): array
    {
        return CashEntry::query()->with('category')->where('kind', CashEntry::KELUAR)->where('status', CashEntry::SAH)
            ->whereBetween('entry_date', [$a->toDateString(), $b->toDateString()])->get()
            ->groupBy(fn (CashEntry $e): string => (string) ($e->category?->name ?? 'Tanpa kategori'))
            ->map(fn ($g, $name): array => ['name' => (string) $name, 'count' => $g->count(), 'total' => (int) $g->sum('amount')])
            ->sortByDesc('total')->values()->all();
    }

    /** @return \Illuminate\Support\Collection<int, CashEntry> */
    public static function latestExpenses(int $limit = 6)
    {
        return CashEntry::query()->with('category')->where('kind', CashEntry::KELUAR)->where('status', CashEntry::SAH)
            ->orderByDesc('entry_date')->orderByDesc('id')->limit($limit)->get();
    }

    /** Saldo kas & bank per hari ini; tracked=false bila saldo awal belum diatur. @return array<string, array{tracked: bool, closing: int}> */
    public static function balances(): array
    {
        return self::once('balances', function (): array {
            $svc = app(CashBook::class);
            $t = now(config('app.timezone'))->startOfDay();
            $out = [];

            foreach (['kas', 'bank'] as $acc) {
                $l = $svc->ledger($acc, $t, $t);
                $out[$acc] = ['tracked' => $l['tracked'], 'closing' => $l['closing']];
            }

            return $out;
        });
    }

    /** Shift yang sedang berjalan & uang tunai yang seharusnya ada di laci kasir. @return array{count: int, cash: int} */
    public static function openShifts(): array
    {
        $open = CashShift::query()->where('status', CashShift::OPEN)->get();

        return ['count' => $open->count(), 'cash' => (int) $open->sum(fn (CashShift $s): int => $s->expectedCash())];
    }

    /** Selisih kas shift yang ditutup bulan ini. @return array{shifts: int, short: int, over: int, net: int} */
    public static function cashDifferenceMonth(): array
    {
        $rows = CashShift::query()->whereNotNull('closed_at')->where('closed_at', '>=', now()->startOfMonth())->get(['difference']);
        $d = $rows->pluck('difference')->map(fn ($v): int => (int) $v);

        return [
            'shifts' => $rows->count(),
            'short' => (int) abs($d->filter(fn ($v): bool => $v < 0)->sum()),
            'over' => (int) $d->filter(fn ($v): bool => $v > 0)->sum(),
            'net' => (int) $d->sum(),
        ];
    }

    /** @return Collection<int, CashShift> */
    public static function shiftsToReview(int $limit = 8)
    {
        return CashShift::query()->with('user')->where('status', CashShift::CLOSED)->orderBy('closed_at')->limit($limit)->get();
    }

    public static function shiftsToReviewCount(): int
    {
        return CashShift::query()->where('status', CashShift::CLOSED)->count();
    }
}
