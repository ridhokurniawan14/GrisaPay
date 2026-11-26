<?php

namespace App\Filament\Widgets;

use App\Enums\PermissionName;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Support\DashboardMetrics;
use Filament\Widgets\Widget;

class LatestExpenses extends Widget
{
    protected static ?int $sort = 12;

    protected string $view = 'filament.widgets.latest-expenses';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $u = auth()->user();

        return (bool) (($u?->can(PermissionName::DashboardTreasurer->value) || $u?->can(PermissionName::DashboardPrincipal->value))
            && ($u?->can(PermissionName::ExpensesView->value) || $u?->can(PermissionName::ExpensesManage->value)));
    }

    protected function getViewData(): array
    {
        return ['rows' => DashboardMetrics::latestExpenses(6), 'url' => ExpenseResource::getUrl('index')];
    }
}
