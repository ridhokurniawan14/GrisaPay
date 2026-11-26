<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\DashboardAccess;
use App\Support\DashboardMetrics;
use Filament\Widgets\Widget;

class TopArrears extends Widget
{
    use DashboardAccess;

    protected static ?int $sort = 10;

    protected string $view = 'filament.widgets.top-arrears';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getViewData(): array
    {
        return ['rows' => DashboardMetrics::topArrears(10)];
    }
}
