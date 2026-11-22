<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\RoleName;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn($state): string => RoleName::tryFrom((string) $state)?->label() ?? (string) $state)
                    ->placeholder('Belum ada peran'),
                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->onColor('success')
                    ->offColor('gray')
                    ->disabled(fn(User $record): bool => ! self::canToggle($record)),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->label('Peran')
                    ->options(fn() => Role::query()->orderBy('id')->pluck('name')
                        ->mapWithKeys(fn(string $name): array => [$name => RoleName::tryFrom($name)?->label() ?? $name])
                        ->all())
                    ->query(fn(Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->role($data['value'])
                        : $query),
                TernaryFilter::make('is_active')
                    ->label('Status akun')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /** Tidak bisa menonaktifkan akun sendiri; hanya yang berhak, dan hanya Super Admin yang boleh mengubah akun Super Admin. */
    private static function canToggle(User $record): bool
    {
        $actor = auth()->user();

        if ($actor === null || $record->is($actor) || ! $actor->can('users.manage')) {
            return false;
        }

        return ! $record->hasRole(RoleName::SuperAdmin->value) || $actor->hasRole(RoleName::SuperAdmin->value);
    }
}
