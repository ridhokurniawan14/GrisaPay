<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\DashboardAccess;
use App\Support\DashboardMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class StudentStatusDonut extends ChartWidget
{
    use DashboardAccess;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        $y = DashboardMetrics::studentStatus()['year'];

        return 'Status pelunasan siswa' . ($y ? ' · TA ' . $y : '');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $s = DashboardMetrics::studentStatus();
        $n = max(1, $s['lunas'] + $s['sebagian'] + $s['belum']);
        $pct = fn(int $v): string => ' · ' . round($v / $n * 100) . '%';

        return [
            'datasets' => [['data' => [$s['lunas'], $s['sebagian'], $s['belum']], 'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444']]],
            'labels' => ['Lunas' . $pct($s['lunas']), 'Bayar sebagian' . $pct($s['sebagian']), 'Belum bayar' . $pct($s['belum'])],
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        {
            plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => c.label + ' — ' + c.raw + ' siswa' } } },
            scales: { x: { display: false }, y: { display: false } },
        }
        JS);
    }
}
