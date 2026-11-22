<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Enums\RoleName;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Peran')
                    ->searchable()
                    ->formatStateUsing(fn(string $state): string => RoleName::tryFrom($state)?->label() ?? $state)
                    ->description(fn(Role $record): string => RoleName::tryFrom($record->name) !== null ? 'Peran bawaan sistem' : 'Peran buatan sendiri'),
                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Jumlah hak akses')
                    ->formatStateUsing(fn($state, Role $record): string => $record->name === RoleName::SuperAdmin->value ? 'Semua' : (string) $state),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Pengguna'),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn(Role $record): bool => RoleName::tryFrom($record->name) !== null),
            ]);
    }
}
