<?php

namespace App\Filament\Resources\CashShifts\Pages;

use App\Filament\Resources\CashShifts\CashShiftResource;
use App\Models\CashShift;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCashShifts extends ListRecords
{
    protected static string $resource = CashShiftResource::class;

    public function getTabs(): array
    {
        return [
            'perlu' => Tab::make('Perlu ditinjau')
                ->badge(CashShift::query()->where('status', CashShift::CLOSED)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CashShift::CLOSED)),
            'berjalan' => Tab::make('Berjalan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CashShift::OPEN)),
            'semua' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return CashShiftResource::canReview() ? 'perlu' : 'semua';
    }
}
