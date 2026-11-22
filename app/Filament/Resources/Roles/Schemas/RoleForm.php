<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Support\PermissionGroups;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $groupCards = [];

        foreach (PermissionName::grouped() as $group => $cases) {
            $groupCards[] = Section::make($group)
                ->schema([
                    CheckboxList::make(PermissionGroups::fieldName($group))
                        ->hiddenLabel()
                        ->options(PermissionName::optionsFor($group))
                        ->bulkToggleable()
                        ->columns(1),
                ])
                ->compact();
        }

        return $schema
            ->components([
                Hidden::make('guard_name')
                    ->default('web'),
                TextInput::make('name')
                    ->label('Nama peran (kode)')
                    ->helperText('Huruf kecil, angka, dan garis bawah. Contoh: admin_tu. Peran bawaan tidak bisa diganti namanya.')
                    ->required()
                    ->maxLength(50)
                    ->regex('/^[a-z0-9_]+$/')
                    ->unique(ignoreRecord: true)
                    ->disabled(fn(?Role $record): bool => $record !== null && RoleName::tryFrom($record->name) !== null),
                Section::make('Hak akses')
                    ->description('Centang hak akses yang dimiliki peran ini. Super Admin otomatis memiliki semua hak akses, sehingga tidak perlu diatur.')
                    ->schema([
                        Grid::make(2)->schema($groupCards),
                    ])
                    ->visible(fn(?Role $record): bool => $record?->name !== RoleName::SuperAdmin->value)
                    ->columnSpanFull(),
            ]);
    }
}
