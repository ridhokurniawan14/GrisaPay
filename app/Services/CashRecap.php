<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Penghitung rekap kas (harian/berkala). Hanya pembayaran berstatus Sah yang dihitung sebagai uang masuk. */
final class CashRecap
{
    /** @return array{0: Carbon, 1: Carbon} */
    public static function range(string $from, string $until): array
    {
        $tz = config('app.timezone');
        $a = Carbon::parse($from, $tz)->startOfDay();
        $b = Carbon::parse($until, $tz)->endOfDay();

        if ($a->gt($b)) {
            [$a, $b] = [$b->copy()->startOfDay(), $a->copy()->endOfDay()];
        }

        return [$a, $b];
    }

    /** @return Collection<int, Payment> */
    public static function payments(Carbon $from, Carbon $until, ?string $method = null, ?int $cashier = null): Collection
    {
        return Payment::query()
            ->with(['student', 'creator', 'verifier', 'allocations.billItem.category', 'allocations.billItem.bill.academicYear'])
            ->whereBetween('paid_at', [$from, $until])
            ->when($method, fn ($q, $m) => $q->where('method', $m))
            ->when($cashier, fn ($q, $c) => $q->where('created_by', $c))
            ->orderBy('paid_at')
            ->get();
    }

    public const GRADE_LABELS = [10 => 'Kelas X (tingkat 1)', 11 => 'Kelas XI (tingkat 2)', 12 => 'Kelas XII (tingkat 3)', 0 => 'Alumni / tanpa kelas'];

    /**
     * Tingkat & kelas siswa pada tahun ajaran pembayaran (dari tagihan tahun itu). Tanpa tagihan tahun itu
     * (mis. alumni yang hanya punya tunggakan) masuk "Alumni / tanpa kelas".
     *
     * @param  Collection<int, Payment>  $payments
     * @return array<int, array{grade: int, class: string}> id pembayaran => penempatan
     */
    public static function placement(Collection $payments): array
    {
        $bills = Bill::query()
            ->whereIn('student_id', $payments->pluck('student_id')->unique()->all())
            ->whereIn('academic_year_id', $payments->pluck('academic_year_id')->unique()->all())
            ->get(['student_id', 'academic_year_id', 'grade', 'classroom_name'])
            ->keyBy(fn ($b): string => $b->student_id.'|'.$b->academic_year_id);

        $out = [];

        foreach ($payments as $p) {
            $bill = $bills->get($p->student_id.'|'.$p->academic_year_id);
            $g = (int) ($bill?->grade ?? 0);
            $g = in_array($g, [10, 11, 12], true) ? $g : 0;
            $out[$p->id] = ['grade' => $g, 'class' => $g === 0 ? 'Alumni / tanpa kelas' : (string) ($bill?->classroom_name ?? '-')];
        }

        return $out;
    }

    /**
     * Daftar pembayaran di balik satu angka/kotak di Rekap Kas.
     * Kunci: total | method:cash|transfer|qris | pending | void | grade:10|11|12|0 | class:NAMA(urlencode) | day:YYYY-MM-DD
     *
     * @param  Collection<int, Payment>  $payments  seluruh pembayaran pada periode & filter yang aktif
     * @return array{title: string, rows: list<array<string, mixed>>, total: int}
     */
    public static function detail(Collection $payments, string $key): array
    {
        [$type, $arg] = array_pad(explode(':', $key, 2), 2, '');
        $sah = fn () => $payments->where('status', Payment::PAID);
        $place = self::placement($payments);

        [$title, $list] = match ($type) {
            'method' => ['Pembayaran '.(Payment::METHODS[$arg] ?? $arg).' (sah)', $sah()->where('method', $arg)],
            'pending' => ['Menunggu verifikasi', $payments->where('status', Payment::PENDING)],
            'void' => ['Dibatalkan (void)', $payments->where('status', Payment::VOID)],
            'grade' => [self::GRADE_LABELS[(int) $arg] ?? 'Tingkat', $sah()->filter(fn (Payment $p): bool => $place[$p->id]['grade'] === (int) $arg)],
            'class' => ['Kelas '.rawurldecode($arg), $sah()->filter(fn (Payment $p): bool => $place[$p->id]['class'] === rawurldecode($arg))],
            'day' => ['Pembayaran sah tanggal '.Carbon::parse($arg)->locale('id')->translatedFormat('d F Y'), $sah()->filter(fn (Payment $p): bool => $p->paid_at->format('Y-m-d') === $arg)],
            default => ['Semua pembayaran sah', $sah()],
        };

        $rows = $list->sortByDesc('paid_at')->values()->map(fn (Payment $p): array => [
            'paid_at' => $p->paid_at->format('d/m/Y'),
            'time' => $p->paid_at->format('H:i').' WIB',
            'receipt_no' => $p->receipt_no ?? '-',
            'student' => $p->student->name,
            'nisn' => $p->student->nisn ?? '-',
            'class' => $place[$p->id]['class'],
            'method' => $p->methodText(),
            'amount' => (int) $p->amount,
            'status' => match ($p->status) { Payment::PAID => 'Sah', Payment::PENDING => 'Menunggu verifikasi', Payment::REJECTED => 'Ditolak', default => 'Void' },
            'creator' => $p->creator?->name ?? '-',
            'for' => $p->allocations->map(fn ($a): string => ($a->billItem?->name ?? '-').' ('.number_format((int) $a->amount, 0, ',', '.').')')->implode('; '),
        ])->all();

        return ['title' => $title, 'rows' => $rows, 'total' => (int) $list->sum('amount')];
    }

    /** @param Collection<int, Payment> $payments */
    public static function summarize(Collection $payments): array
    {
        $sah = $payments->where('status', Payment::PAID);

        $place = self::placement($sah);
        $gradeOf = fn (Payment $p): int => $place[$p->id]['grade'];

        $byMethod = [];

        foreach (Payment::METHODS as $key => $label) {
            $m = $sah->where('method', $key);
            $byMethod[$key] = ['label' => $label, 'count' => $m->count(), 'total' => (int) $m->sum('amount')];
        }

        $byDay = $sah
            ->groupBy(fn (Payment $p): string => $p->paid_at->format('Y-m-d'))
            ->map(fn (Collection $g, string $day): array => [
                'date' => $day,
                'count' => $g->count(),
                'cash' => (int) $g->where('method', Payment::CASH)->sum('amount'),
                'transfer' => (int) $g->where('method', Payment::TRANSFER)->sum('amount'),
                'qris' => (int) $g->where('method', Payment::QRIS)->sum('amount'),
                'total' => (int) $g->sum('amount'),
                'g10' => (int) $g->filter(fn (Payment $p): bool => $gradeOf($p) === 10)->sum('amount'),
                'g11' => (int) $g->filter(fn (Payment $p): bool => $gradeOf($p) === 11)->sum('amount'),
                'g12' => (int) $g->filter(fn (Payment $p): bool => $gradeOf($p) === 12)->sum('amount'),
                'g0' => (int) $g->filter(fn (Payment $p): bool => $gradeOf($p) === 0)->sum('amount'),
            ])
            ->sortKeys()
            ->values()
            ->all();

        $categories = [];
        $items = [];

        foreach ($sah as $payment) {
            foreach ($payment->allocations as $a) {
                $catName = (string) ($a->billItem?->category?->receipt_label ?? 'Lainnya');
                $itemName = (string) ($a->billItem?->name ?? '-');
                $year = (string) ($a->billItem?->bill?->academicYear?->code ?? '-');
                $ik = $catName.'|'.$itemName.'|'.$year;
                $items[$ik] ??= ['category' => $catName, 'name' => $itemName, 'year' => $year, 'count' => 0, 'total' => 0, 'payments' => []];
                $items[$ik]['count']++;
                $items[$ik]['total'] += (int) $a->amount;
                $items[$ik]['payments'][$payment->id] = true;

                $name = (string) ($a->billItem?->category?->receipt_label ?? 'Lainnya');
                $categories[$name] ??= ['name' => $name, 'count' => 0, 'total' => 0];
                $categories[$name]['count']++;
                $categories[$name]['total'] += (int) $a->amount;
            }
        }

        $byCategory = collect($categories)->sortByDesc('total')->values()->all();

        $catTotals = collect($items)->groupBy('category')->map(fn ($g) => $g->sum('total'));
        $byItem = collect($items)
            ->map(fn (array $i): array => ['category' => $i['category'], 'name' => $i['name'], 'year' => $i['year'], 'count' => count($i['payments']), 'total' => $i['total']])
            ->sort(fn (array $a, array $b): int => [$catTotals[$b['category']], $a['category'], $b['total']] <=> [$catTotals[$a['category']], $b['category'], $a['total']])
            ->values()
            ->all();

        $byCashier = $sah
            ->groupBy(fn (Payment $p): string => (string) ($p->creator?->name ?? '-'))
            ->map(fn (Collection $g, string $name): array => ['name' => $name, 'count' => $g->count(), 'total' => (int) $g->sum('amount')])
            ->sortByDesc('total')
            ->values()
            ->all();

        $gradeLabels = [10 => 'Kelas X (tingkat 1)', 11 => 'Kelas XI (tingkat 2)', 12 => 'Kelas XII (tingkat 3)', 0 => 'Alumni / tanpa kelas'];
        $byGrade = [];

        foreach ($gradeLabels as $grade => $label) {
            $g = $sah->filter(fn (Payment $p): bool => $gradeOf($p) === $grade);
            $byGrade[] = ['grade' => $grade, 'label' => $label, 'students' => $g->pluck('student_id')->unique()->count(), 'count' => $g->count(), 'total' => (int) $g->sum('amount')];
        }

        $classes = [];

        foreach ($sah as $p) {
            $grade = $place[$p->id]['grade'];
            $name = $place[$p->id]['class'];
            $classes[$name] ??= ['grade' => $grade, 'name' => $name, 'students' => [], 'count' => 0, 'total' => 0];
            $classes[$name]['students'][$p->student_id] = true;
            $classes[$name]['count']++;
            $classes[$name]['total'] += (int) $p->amount;
        }

        $byClass = collect($classes)
            ->map(fn (array $c): array => ['grade' => $c['grade'], 'label' => $gradeLabels[$c['grade']] ?? '-', 'name' => $c['name'], 'students' => count($c['students']), 'count' => $c['count'], 'total' => $c['total']])
            ->sort(fn (array $a, array $b): int => [$a['grade'] === 0 ? 99 : $a['grade'], $a['name']] <=> [$b['grade'] === 0 ? 99 : $b['grade'], $b['name']])
            ->values()
            ->all();

        $pending = $payments->where('status', Payment::PENDING);
        $void = $payments->where('status', Payment::VOID);

        return [
            'total' => (int) $sah->sum('amount'),
            'count' => $sah->count(),
            'by_method' => $byMethod,
            'by_day' => $byDay,
            'by_category' => $byCategory,
            'by_item' => $byItem,
            'by_grade' => $byGrade,
            'by_class' => $byClass,
            'by_cashier' => $byCashier,
            'pending' => ['count' => $pending->count(), 'total' => (int) $pending->sum('amount')],
            'void' => ['count' => $void->count(), 'total' => (int) $void->sum('amount')],
        ];
    }
}
