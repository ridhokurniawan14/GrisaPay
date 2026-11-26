<?php

namespace App\Filament\Widgets;

use App\Enums\PermissionName;
use App\Filament\Pages\BukuKas;
use App\Filament\Resources\CashShifts\CashShiftResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Widgets\Concerns\DashboardAccess;
use App\Models\Payment;
use App\Support\DashboardMetrics;
use Filament\Widgets\Widget;

/** Kartu ringkasan dashboard keuangan, dikelompokkan: uang masuk, penagihan, perlu tindakan. */
class KpiCards extends Widget
{
    use DashboardAccess;

    protected static ?int $sort = 1;

    protected string $view = 'filament.widgets.kpi-cards';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    private static function rp(int $n): string
    {
        return 'Rp '.number_format($n, 0, ',', '.');
    }

    /** @return array{text: string, up: bool}|null */
    private static function delta(int $now, int $before): ?array
    {
        if ($before === 0) {
            return $now > 0 ? ['text' => 'baru', 'up' => true] : null;
        }

        $p = (int) round(($now - $before) / $before * 100);

        return ['text' => ($p >= 0 ? '+' : '').$p.'%', 'up' => $p >= 0];
    }

    protected function getViewData(): array
    {
        $today = DashboardMetrics::period('today')['summary'];
        $yesterday = DashboardMetrics::period('yesterday')['summary'];
        $monthData = DashboardMetrics::period('month');
        $month = $monthData['summary'];
        $prev = DashboardMetrics::period('prev_month')['summary'];
        $voids = DashboardMetrics::voidRequests();
        $st = DashboardMetrics::studentStatus();
        $students = $st['lunas'] + $st['sebagian'] + $st['belum'];
        $collect = $st['due'] > 0 ? (int) round($st['paid'] / $st['due'] * 100) : 0;
        $out = array_sum(array_column(DashboardMetrics::outstandingByGrade(), 'remaining'));
        $avgDay = (int) round($month['total'] / max(1, now()->day));
        $series = DashboardMetrics::dailySeries(30);
        $spark = array_slice($series['totals'], -14);
        $open = DashboardMetrics::openShifts();
        $diff = DashboardMetrics::cashDifferenceMonth();
        $reviewN = DashboardMetrics::shiftsToReviewCount();
        $payers = $monthData['payments']->where('status', Payment::PAID)->pluck('student_id')->unique()->count();
        $canReview = CashShiftResource::canReview();

        $groups = [
            ['title' => 'Uang masuk', 'cards' => [
                ['label' => 'Hari ini', 'value' => self::rp($today['total']), 'icon' => 'heroicon-o-banknotes', 'tone' => 'success',
                    'sub' => $today['count'].' transaksi · tunai '.self::rp($today['by_method']['cash']['total']),
                    'delta' => self::delta($today['total'], $yesterday['total']), 'deltaNote' => 'vs kemarin '.self::rp($yesterday['total']), 'spark' => $spark],
                ['label' => 'Bulan ini', 'value' => self::rp($month['total']), 'icon' => 'heroicon-o-calendar-days', 'tone' => 'primary',
                    'sub' => $month['count'].' transaksi', 'delta' => self::delta($month['total'], $prev['total']), 'deltaNote' => 'vs bulan lalu (periode sama)', 'spark' => $spark],
                ['label' => 'Rata-rata per hari', 'value' => self::rp($avgDay), 'icon' => 'heroicon-o-chart-bar', 'tone' => 'info',
                    'sub' => 'Transfer '.self::rp($month['by_method']['transfer']['total']).' · QRIS '.self::rp($month['by_method']['qris']['total'])],
                ['label' => 'Siswa membayar bulan ini', 'value' => (string) $payers, 'icon' => 'heroicon-o-user-group', 'tone' => 'primary',
                    'sub' => 'dari '.$students.' siswa bertagihan'],
            ]],
            ['title' => 'Penagihan'.($st['year'] ? ' · TA '.$st['year'] : ''), 'cards' => [
                ['label' => 'Tingkat penagihan', 'value' => $collect.'%', 'icon' => 'heroicon-o-chart-pie', 'tone' => $collect >= 75 ? 'success' : ($collect >= 40 ? 'warning' : 'danger'),
                    'sub' => self::rp($st['paid']).' dari '.self::rp($st['due']), 'progress' => $collect],
                ['label' => 'Sisa tagihan', 'value' => self::rp($out), 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => 'danger', 'sub' => 'Belum dibayar, semua tahun ajaran'],
                ['label' => 'Siswa lunas', 'value' => $st['lunas'].' / '.$students, 'icon' => 'heroicon-o-check-badge', 'tone' => 'success',
                    'sub' => $st['sebagian'].' sebagian · '.$st['belum'].' belum bayar', 'progress' => $students > 0 ? (int) round($st['lunas'] / $students * 100) : 0],
                ['label' => 'Menunggu verifikasi', 'value' => (string) $month['pending']['count'], 'icon' => 'heroicon-o-clock', 'tone' => $month['pending']['count'] > 0 ? 'warning' : 'gray',
                    'sub' => self::rp($month['pending']['total']).' belum dihitung (bulan ini)', 'url' => PaymentResource::getUrl('index')],
            ]],
            ['title' => 'Kas & perlu tindakan', 'cards' => [
                ['label' => 'Shift menunggu tinjauan', 'value' => (string) $reviewN, 'icon' => 'heroicon-o-archive-box', 'tone' => $reviewN > 0 ? 'warning' : 'success',
                    'sub' => $reviewN > 0 ? ($canReview ? 'Klik untuk meninjau' : 'Menunggu bendahara') : 'Semua sudah ditinjau',
                    'url' => $canReview ? CashShiftResource::getUrl('index').'?activeTab=perlu' : null],
                ['label' => 'Shift berjalan', 'value' => (string) $open['count'], 'icon' => 'heroicon-o-play-circle', 'tone' => $open['count'] > 0 ? 'info' : 'gray',
                    'sub' => $open['count'] > 0 ? 'Tunai di laci seharusnya '.self::rp($open['cash']) : 'Tidak ada kasir yang sedang buka'],
                ['label' => 'Selisih kas bulan ini', 'value' => $diff['net'] === 0 ? 'Cocok' : ($diff['net'] < 0 ? '−' : '+').self::rp(abs($diff['net'])), 'icon' => 'heroicon-o-scale',
                    'tone' => $diff['net'] === 0 ? 'success' : ($diff['net'] < 0 ? 'danger' : 'warning'),
                    'sub' => $diff['shifts'].' shift · kurang '.self::rp($diff['short']).' · lebih '.self::rp($diff['over'])],
                ['label' => 'Void menunggu ACC', 'value' => (string) $voids['count'], 'icon' => 'heroicon-o-x-circle', 'tone' => $voids['count'] > 0 ? 'danger' : 'success',
                    'sub' => $voids['count'] > 0 ? self::rp($voids['total']).' perlu keputusan bendahara' : 'Tidak ada permintaan', 'url' => PaymentResource::getUrl('index')],
            ]],
        ];

        if (auth()->user()?->can(PermissionName::ExpensesView->value) || auth()->user()?->can(PermissionName::ExpensesManage->value)) {
            $tz = config('app.timezone');
            $mFrom = now($tz)->startOfMonth();
            $mTo = now($tz)->endOfDay();
            $pFrom = now($tz)->subMonthNoOverflow()->startOfMonth();
            $pTo = now($tz)->subMonthNoOverflow()->endOfDay();
            $expM = DashboardMetrics::expenses($mFrom, $mTo);
            $expP = DashboardMetrics::expenses($pFrom, $pTo);
            $expT = DashboardMetrics::expenses(now($tz)->startOfDay(), now($tz)->endOfDay());
            $other = DashboardMetrics::otherIncome($mFrom, $mTo);
            $surplus = $month['total'] + $other - $expM['total'];
            $bal = DashboardMetrics::balances();
            $topCat = DashboardMetrics::expensesByCategory($mFrom, $mTo)[0] ?? null;
            $bookUrl = BukuKas::getUrl();
            $dExp = self::delta($expM['total'], $expP['total']);

            if ($dExp !== null) {
                $dExp['up'] = ! $dExp['up']; // pengeluaran naik = kurang baik
            }

            $balCard = fn (string $acc, string $label, string $icon): array => $bal[$acc]['tracked']
                ? ['label' => $label, 'value' => self::rp($bal[$acc]['closing']), 'icon' => $icon, 'tone' => $bal[$acc]['closing'] >= 0 ? 'primary' : 'danger', 'sub' => 'Per hari ini · lihat Buku Kas', 'url' => $bookUrl]
                : ['label' => $label, 'value' => 'Belum diatur', 'icon' => $icon, 'tone' => 'gray', 'sub' => 'Isi saldo awal di Buku Kas', 'url' => $bookUrl];

            $groups[] = ['title' => 'Pengeluaran & saldo', 'cards' => [
                ['label' => 'Pengeluaran bulan ini', 'value' => self::rp($expM['total']), 'icon' => 'heroicon-o-receipt-percent', 'tone' => 'warning',
                    'sub' => $expM['count'].' catatan · hari ini '.self::rp($expT['total']).($topCat ? ' · terbesar: '.$topCat['name'] : ''),
                    'delta' => $dExp, 'deltaNote' => 'vs bulan lalu (periode sama)', 'url' => ExpenseResource::getUrl('index')],
                ['label' => $surplus >= 0 ? 'Surplus bulan ini' : 'Defisit bulan ini', 'value' => self::rp(abs($surplus)), 'icon' => 'heroicon-o-scale', 'tone' => $surplus >= 0 ? 'success' : 'danger',
                    'sub' => 'Pembayaran siswa '.self::rp($month['total']).' + pemasukan lain '.self::rp($other).' − pengeluaran'],
                $balCard('kas', 'Saldo kas (tunai)', 'heroicon-o-banknotes'),
                $balCard('bank', 'Saldo bank', 'heroicon-o-building-library'),
            ]];
        }

        return ['groups' => $groups];
    }
}
