<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CashShifts\CashShiftResource;
use App\Support\DashboardMetrics;
use Filament\Widgets\Widget;

class ShiftReviewQueue extends Widget
{
    protected static ?int $sort = 9;

    protected string $view = 'filament.widgets.shift-review-queue';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return CashShiftResource::canReview();
    }

    protected function getViewData(): array
    {
        return [
            'shifts' => DashboardMetrics::shiftsToReview(),
            'url' => CashShiftResource::getUrl('index').'?activeTab=perlu',
        ];
    }
}
