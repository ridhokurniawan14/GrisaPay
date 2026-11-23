<?php

namespace App\Filament\Resources\DiscountTypes\Pages;

use App\Filament\Resources\DiscountTypes\DiscountTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ManageDiscountTypes extends ListRecords
{
    protected static string $resource = DiscountTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah jenis diskon')];
    }
}
