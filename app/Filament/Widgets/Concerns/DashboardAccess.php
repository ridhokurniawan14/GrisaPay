<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\PermissionName;

/** Widget dashboard keuangan hanya untuk bendahara (dashboard.treasurer) dan kepala sekolah (dashboard.principal). */
trait DashboardAccess
{
    public static function canView(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can(PermissionName::DashboardTreasurer->value) || $u?->can(PermissionName::DashboardPrincipal->value));
    }
}
