<?php

namespace App\Filament\Widgets;

use App\Enums\PermissionName;
use App\Support\DashboardMetrics;
use Filament\Widgets\Widget;

class ExpenseByCategory extends Widget
{
    protected static ?int $sort = 11;

    protected string $view = 'filament.widgets.expense-by-category';

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
        $tz = config('app.timezone');

        return [
            'month' => DashboardMetrics::expensesByCategory(now($tz)->startOfMonth(), now($tz)->endOfDay()),
            'today' => DashboardMetrics::expensesByCategory(now($tz)->startOfDay(), now($tz)->endOfDay()),
        ];
    }
}
