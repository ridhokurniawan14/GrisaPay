<?php

namespace App\Filament\Resources\FeeComponents;

use App\Enums\PermissionName;
use App\Filament\Resources\FeeComponents\Pages\ListFeeComponents;
use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\FeeComponent;
use App\Support\TariffAudit;
use BackedEnum;
use DomainException;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class FeeComponentResource extends Resource
{
    protected static ?string $model = FeeComponent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Tarif';

    protected static ?string $modelLabel = 'komponen tarif';

    protected static ?string $pluralModelLabel = 'Tarif';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::TariffsManage->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('academic_year_id')
                ->label('Tahun ajaran')
                ->options(fn() => AcademicYear::query()->orderByDesc('start_date')->pluck('code', 'id')->all())
                ->default(fn() => AcademicYear::current()?->id)
                ->required(),
            Select::make('grade')
                ->label('Tingkat')
                ->options([10 => 'Kelas 10 (X)', 11 => 'Kelas 11 (XI)', 12 => 'Kelas 12 (XII)'])
                ->required()
                ->helperText('Tarif sama untuk semua jurusan di tingkat ini.'),
            Select::make('fee_category_id')
                ->label('Kategori (tampil di struk)')
                ->options(fn() => FeeCategory::query()
                    ->where('is_active', true)
                    ->where('code', '!=', FeeCategory::CARRYOVER)
                    ->orderBy('sort_order')
                    ->pluck('receipt_label', 'id')
                    ->all())
                ->required()
                ->helperText('Tunggakan sebelumnya dihitung otomatis dari tagihan tahun lalu, tidak diisi di sini.'),
            TextInput::make('name')
                ->label('Nama rincian')
                ->required()
                ->maxLength(150),
            TextInput::make('amount')
                ->label('Nominal')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->prefix('Rp')
                ->required()
                ->helperText('Mengubah atau menghapus tarif TIDAK mengubah tagihan yang sudah dibuat. Hanya tagihan baru yang memakainya.'),
            TextInput::make('sort_order')
                ->label('Urutan')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['category', 'academicYear']))
            ->defaultSort('grade')
            ->columns([
                TextColumn::make('academicYear.code')->label('Tahun ajaran')->sortable(),
                TextColumn::make('grade')
                    ->label('Tingkat')
                    ->badge()
                    ->formatStateUsing(fn($state): string => "Kelas {$state}")
                    ->sortable(),
                TextColumn::make('category.receipt_label')->label('Kategori'),
                TextColumn::make('name')->label('Rincian')->searchable(),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn($state): string => 'Rp ' . number_format((int) $state, 0, ',', '.'))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('sort_order')->label('Urutan')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('academic_year_id')
                    ->label('Tahun ajaran')
                    ->options(fn() => AcademicYear::query()->orderByDesc('start_date')->pluck('code', 'id')->all())
                    ->default(fn() => AcademicYear::current()?->id),
                SelectFilter::make('grade')
                    ->label('Tingkat')
                    ->options([10 => 'Kelas 10', 11 => 'Kelas 11', 12 => 'Kelas 12']),
            ])
            ->recordActions([
                EditAction::make()
                    ->before(function (array $data, FeeComponent $record): void {
                        TariffAudit::updated($record, $data);
                    }),
                DeleteAction::make()
                    ->before(function (FeeComponent $record): void {
                        TariffAudit::deleted($record);
                    }),
            ])
            // Centang beberapa (atau "pilih semua") lalu hapus sekaligus. Tagihan yang sudah dibuat tidak berubah.
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus terpilih')
                        ->modalHeading('Hapus tarif terpilih?')
                        ->modalDescription('Tarif yang dicentang dihapus. Tagihan yang sudah dibuat TIDAK berubah; hanya tagihan baru yang terpengaruh. Tercatat di log aktivitas.')
                        ->action(function (Collection $records): void {
                            $deleted = 0;
                            $locked = 0;

                            foreach ($records as $record) {
                                try {
                                    TariffAudit::deleted($record);
                                    $record->delete();
                                    $deleted++;
                                } catch (DomainException) {
                                    $locked++; // tahun ajaran sudah ditutup buku
                                }
                            }

                            Notification::make()
                                ->color($locked > 0 ? 'warning' : 'success')
                                ->title("{$deleted} tarif dihapus")
                                ->body($locked > 0 ? "{$locked} tarif tidak bisa dihapus karena tahun ajarannya sudah ditutup buku." : null)
                                ->duration(8000)
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFeeComponents::route('/')];
    }
}
