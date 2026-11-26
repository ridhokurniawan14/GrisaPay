<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\DashboardAccess;
use App\Support\DashboardMetrics;
use Filament\Widgets\Widget;

class TodayByCashier extends Widget
{
    use DashboardAccess;

    protected static ?int $sort = 8;

    protected string $view = 'filament.widgets.today-by-cashier';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    protected function getViewData(): array
    {
        return ['rows' => DashboardMetrics::byCashier('today'), 'month' => DashboardMetrics::byCashier('month')];
    }
}
