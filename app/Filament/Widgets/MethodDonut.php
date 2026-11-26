<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\DashboardAccess;
use App\Support\DashboardMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class MethodDonut extends ChartWidget
{
    use DashboardAccess;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        return 'Metode pembayaran bulan ini';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $m = DashboardMetrics::period('month')['summary']['by_method'];
        $total = max(1, array_sum(array_map(fn($x) => $x['total'], $m)));

        return [
            'datasets' => [['data' => array_values(array_map(fn($x) => $x['total'], $m)), 'backgroundColor' => ['#10b981', '#6366f1', '#f59e0b', '#ec4899']]],
            'labels' => array_values(array_map(fn($x) => $x['label'] . ' · ' . round($x['total'] / $total * 100) . '%', $m)),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        {
            plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => c.label + ' — Rp ' + Number(c.raw).toLocaleString('id-ID') } } },
            scales: { x: { display: false }, y: { display: false } },
        }
        JS);
    }
}
