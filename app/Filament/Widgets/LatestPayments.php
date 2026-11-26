<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\DashboardAccess;
use App\Support\DashboardMetrics;
use Filament\Widgets\Widget;

class LatestPayments extends Widget
{
    use DashboardAccess;

    protected static ?int $sort = 7;

    protected string $view = 'filament.widgets.latest-payments';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getViewData(): array
    {
        return DashboardMetrics::latestPayments(10) + ['url' => \App\Filament\Resources\Payments\PaymentResource::getUrl('index')];
    }
}
