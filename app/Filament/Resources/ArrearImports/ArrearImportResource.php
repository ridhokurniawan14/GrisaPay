<?php

namespace App\Filament\Resources\ArrearImports;

use App\Enums\PermissionName;
use App\Filament\Resources\ArrearImports\Pages\ListArrearImports;
use App\Models\ArrearImport;
use App\Services\ArrearImporter;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ArrearImportResource extends Resource
{
    protected static ?string $model = ArrearImport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Riwayat Impor Tunggakan';

    protected static ?string $modelLabel = 'impor tunggakan';

    protected static ?string $pluralModelLabel = 'Riwayat Impor Tunggakan';

    protected static ?int $navigationSort = 60;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::BillsManage->value);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('importer'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('filename')->label('Berkas')->searchable(),
                TextColumn::make('items_count')->label('Baris')->alignCenter(),
                TextColumn::make('total_amount')->label('Total')->alignEnd()
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((int) $state, 0, ',', '.')),
                TextColumn::make('importer.name')->label('Oleh'),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => $state === ArrearImport::ACTIVE ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
            ])
            ->recordActions([
                Action::make('undo')
                    ->label('Batalkan impor')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (ArrearImport $record): bool => $record->status === ArrearImport::ACTIVE
                        && (bool) auth()->user()?->can(PermissionName::BillsManage->value))
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan impor tunggakan')
                    ->modalDescription('Semua tunggakan dari berkas ini dihapus. Hanya bisa jika belum ada yang dibayar atau didiskon.')
                    ->action(function (ArrearImport $record): void {
                        abort_unless(auth()->user()?->can(PermissionName::BillsManage->value), 403);

                        try {
                            app(ArrearImporter::class)->undo($record, auth()->user());
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title('Gagal membatalkan')->body($e->getMessage())->duration(8000)->send();

                            return;
                        }

                        Notification::make()->success()->title('Impor dibatalkan')->duration(5000)->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListArrearImports::route('/')];
    }
}
