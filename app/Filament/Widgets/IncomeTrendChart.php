<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\DashboardAccess;
use App\Support\DashboardMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class IncomeTrendChart extends ChartWidget
{
    use DashboardAccess;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    protected ?string $maxHeight = '280px';

    public function getHeading(): ?string
    {
        return 'Uang masuk 30 hari terakhir';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $s = DashboardMetrics::dailySeries(30);

        return [
            'datasets' => [
                ['label' => 'Total (sah)', 'data' => $s['totals'], 'backgroundColor' => '#6366f1'],
                ['label' => 'Tunai', 'data' => $s['cash'], 'backgroundColor' => '#10b981'],
            ],
            'labels' => $s['labels'],
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        {
            plugins: {
                legend: { display: true },
                tooltip: { callbacks: { label: (c) => c.dataset.label + ': Rp ' + Number(c.raw).toLocaleString('id-ID') } },
            },
            scales: { y: { ticks: { callback: (v) => 'Rp ' + Number(v).toLocaleString('id-ID') } } },
        }
        JS);
    }
}
