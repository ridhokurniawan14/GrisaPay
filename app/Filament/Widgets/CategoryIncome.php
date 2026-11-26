<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\DashboardAccess;
use App\Support\DashboardMetrics;
use Filament\Widgets\Widget;

class CategoryIncome extends Widget
{
    use DashboardAccess;

    protected static ?int $sort = 5;

    protected string $view = 'filament.widgets.category-income';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    protected function getViewData(): array
    {
        return ['today' => DashboardMetrics::period('today')['summary']['by_category'], 'month' => DashboardMetrics::period('month')['summary']['by_category']];
    }
}
