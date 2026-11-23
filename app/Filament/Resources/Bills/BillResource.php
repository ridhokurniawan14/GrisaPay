<?php

namespace App\Filament\Resources\Bills;

use App\Enums\PermissionName;
use App\Exports\BillReportBuilder;
use App\Exports\ReportDownloader;
use App\Filament\Resources\Bills\Pages\ListBills;
use App\Models\AcademicYear;
use App\Models\Bill;
use App\Services\BillSummary;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class BillResource extends Resource
{
    protected static ?string $model = Bill::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Tagihan Siswa';

    protected static ?string $modelLabel = 'tagihan';

    protected static ?string $pluralModelLabel = 'Tagihan Siswa';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::BillsView->value);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['student', 'academicYear'])
                ->withSum('items as items_amount', 'amount')
                ->withSum('items as items_discount', 'discount_amount'))
            ->defaultSort('classroom_name')
            ->columns([
                TextColumn::make('student.name')->label('Siswa')->searchable()->sortable(),
                TextColumn::make('student.nisn')->label('NISN')->searchable(),
                TextColumn::make('classroom_name')->label('Kelas')->searchable()->sortable(),
                TextColumn::make('academicYear.code')->label('Tahun ajaran'),
                TextColumn::make('items_amount')
                    ->label('Tagihan')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((int) $state, 0, ',', '.'))
                    ->alignEnd(),
                TextColumn::make('items_discount')
                    ->label('Diskon')
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((int) $state, 0, ',', '.'))
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('academic_year_id')
                    ->label('Tahun ajaran')
                    ->options(fn () => AcademicYear::query()->orderByDesc('start_date')->pluck('code', 'id')->all())
                    ->default(fn () => AcademicYear::current()?->id),
                SelectFilter::make('grade')->label('Tingkat')
                    ->options([10 => 'Kelas 10', 11 => 'Kelas 11', 12 => 'Kelas 12']),
                SelectFilter::make('classroom_name')->label('Kelas')
                    ->options(fn () => Bill::query()->distinct()->orderBy('classroom_name')->pluck('classroom_name', 'classroom_name')->all())
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Rincian')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Rincian tagihan')
                    ->modalWidth('3xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (Bill $record) => view('filament.bill-summary', [
                        'bill' => $record,
                        'student' => $record->student,
                        'year' => $record->academicYear,
                        'summary' => BillSummary::forStudent($record->student, $record->academicYear),
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkExcel')
                        ->label('Unduh Excel (yang dipilih)')
                        ->icon('heroicon-o-table-cells')
                        ->action(fn (Collection $records) => ReportDownloader::respond(
                            BillReportBuilder::build($records, ['Hanya baris yang dipilih']), 'excel'))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulkPdf')
                        ->label('Unduh PDF (yang dipilih)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(fn (Collection $records) => ReportDownloader::respond(
                            BillReportBuilder::build($records, ['Hanya baris yang dipilih']), 'pdf'))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBills::route('/')];
    }
}
