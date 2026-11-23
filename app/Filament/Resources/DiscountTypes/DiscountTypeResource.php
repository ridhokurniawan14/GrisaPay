<?php

namespace App\Filament\Resources\DiscountTypes;

use App\Enums\PermissionName;
use App\Filament\Resources\DiscountTypes\Pages\ManageDiscountTypes;
use App\Models\Discount;
use App\Models\DiscountType;
use App\Models\FeeCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class DiscountTypeResource extends Resource
{
    protected static ?string $model = DiscountType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Jenis Diskon';

    protected static ?string $modelLabel = 'jenis diskon';

    protected static ?string $pluralModelLabel = 'Jenis Diskon';

    protected static ?int $navigationSort = 40;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::TariffsManage->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama jenis diskon')->required()->maxLength(100)->unique(ignoreRecord: true),
            Select::make('default_scope')->label('Cakupan bawaan')
                ->options([Discount::SCOPE_BILL => 'Seluruh tagihan', Discount::SCOPE_CATEGORY => 'Per kategori', Discount::SCOPE_ITEM => 'Per rincian'])
                ->default(Discount::SCOPE_BILL)->required()->live(),
            Select::make('default_fee_category_id')->label('Kategori bawaan')
                ->options(fn () => FeeCategory::query()->where('code', '!=', FeeCategory::CARRYOVER)->orderBy('sort_order')->pluck('receipt_label', 'id')->all())
                ->visible(fn ($get): bool => $get('default_scope') === Discount::SCOPE_CATEGORY),
            Select::make('default_mode')->label('Bentuk nilai bawaan')
                ->options([Discount::MODE_PERCENT => 'Persen (%)', Discount::MODE_AMOUNT => 'Nominal (Rp)'])
                ->default(Discount::MODE_PERCENT)->required(),
            TextInput::make('default_value')->label('Nilai bawaan')->numeric()->integer()->minValue(1)
                ->helperText('Hanya mengisi form pengajuan secara otomatis. Petugas tetap bisa mengubahnya.'),
            TextInput::make('sort_order')->label('Urutan tampil')->numeric()->integer()->minValue(0)->default(0)
                ->helperText('Mengatur susunan di daftar pilihan saat mengajukan diskon: angka kecil tampil lebih dulu. Tidak memengaruhi nilai diskon.'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Jenis diskon')->searchable(),
                TextColumn::make('default_scope')->label('Cakupan bawaan')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        Discount::SCOPE_CATEGORY => 'Per kategori',
                        Discount::SCOPE_ITEM => 'Per rincian',
                        default => 'Seluruh tagihan',
                    }),
                TextColumn::make('default_value')->label('Nilai bawaan')
                    ->formatStateUsing(fn ($state, DiscountType $record): string => $state === null ? '-'
                        : ($record->default_mode === Discount::MODE_PERCENT ? "{$state}%" : 'Rp '.number_format((int) $state, 0, ',', '.'))),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, DiscountType $record): void {
                        if (Discount::query()->where('discount_type_id', $record->id)->exists()) {
                            \Filament\Notifications\Notification::make()->danger()
                                ->title('Tidak bisa dihapus')->body('Jenis ini sudah dipakai pengajuan diskon. Nonaktifkan saja.')->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDiscountTypes::route('/')];
    }
}
