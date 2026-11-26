<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\DashboardAccess;
use App\Support\DashboardMetrics;
use Filament\Widgets\Widget;

class OutstandingByGrade extends Widget
{
    use DashboardAccess;

    protected static ?int $sort = 6;

    protected string $view = 'filament.widgets.outstanding-by-grade';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    protected function getViewData(): array
    {
        return ['rows' => DashboardMetrics::outstandingByGrade()];
    }
}
