<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\RoleName;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email (untuk login)')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Kosongkan jika tidak ingin mengganti password.'
                        : 'Minimal 8 karakter.'),
                Select::make('roles')
                    ->label('Peran')
                    ->relationship(
                        'roles',
                        'name',
                        // Hanya Super Admin yang boleh memberikan peran Super Admin (mencegah kenaikan hak akses).
                        modifyQueryUsing: fn ($query) => auth()->user()?->hasRole(RoleName::SuperAdmin->value)
                            ? $query->orderBy('id')
                            : $query->where('name', '!=', RoleName::SuperAdmin->value)->orderBy('id'),
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record): string => RoleName::tryFrom($record->name)?->label() ?? $record->name)
                    ->multiple()
                    ->preload()
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                    ->helperText(fn (?User $record): ?string => ($record?->is(auth()->user()) ?? false)
                        ? 'Anda tidak bisa mengubah peran akun sendiri.'
                        : null),
                Toggle::make('is_active')
                    ->label('Akun aktif')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                    ->helperText('Akun nonaktif tidak bisa masuk. Akun tidak dihapus agar jejak audit keuangan tetap utuh.'),
            ]);
    }
}