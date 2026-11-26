<?php

namespace App\Filament\Resources\Discounts;

use App\Enums\PermissionName;
use App\Exports\DiscountReportBuilder;
use App\Exports\ReportDownloader;
use App\Filament\Resources\Discounts\Pages\ListDiscounts;
use App\Models\AcademicYear;
use App\Models\Discount;
use App\Services\DiscountService;
use App\Support\Nav;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use UnitEnum;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Diskon';

    protected static ?string $modelLabel = 'diskon';

    protected static ?string $pluralModelLabel = 'Diskon';

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can(PermissionName::DiscountsRequest->value) || $user?->can(PermissionName::DiscountsApprove->value));
    }

    /** Angka di menu: pengajuan diskon yang menunggu keputusan (hanya untuk yang berhak menyetujui). */
    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can(PermissionName::DiscountsApprove->value)) {
            return null;
        }

        $count = Discount::query()->where('status', Discount::REQUESTED)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['bill.student', 'bill.academicYear', 'type', 'category', 'billItem', 'requester', 'decider']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('bill.student.name')->label('Siswa')->searchable(),
                TextColumn::make('bill.classroom_name')->label('Kelas'),
                TextColumn::make('type.name')->label('Jenis'),
                TextColumn::make('scope')->label('Cakupan')
                    ->formatStateUsing(fn ($state, Discount $record): string => $record->scopeText()),
                TextColumn::make('value')->label('Nilai')
                    ->formatStateUsing(fn ($state, Discount $record): string => $record->valueText()),
                TextColumn::make('granted_amount')->label('Potongan')
                    ->formatStateUsing(fn ($state, Discount $record): string => $record->status === Discount::APPROVED
                        ? 'Rp '.number_format((int) $state, 0, ',', '.') : '-')
                    ->alignEnd(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Discount::APPROVED => 'success',
                        Discount::REJECTED => 'danger',
                        Discount::REVOKED => 'gray',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('requester.name')->label('Pengaju')->toggleable(),
                TextColumn::make('decider.name')->label('Diputuskan oleh')->toggleable(),
                TextColumn::make('reason')->label('Alasan')->limit(40)->tooltip(fn (Discount $record): string => $record->reason)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options([
                    Discount::REQUESTED => 'Diajukan', Discount::APPROVED => 'Disetujui',
                    Discount::REJECTED => 'Ditolak', Discount::REVOKED => 'Dicabut',
                ]),
                SelectFilter::make('year')->label('Tahun ajaran')
                    ->options(fn () => AcademicYear::query()->orderByDesc('start_date')->pluck('code', 'id')->all())
                    ->default(fn () => AcademicYear::current()?->id)
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('bill', fn ($q) => $q->where('academic_year_id', $data['value']))
                        : $query),
            ])
            ->recordActions([
                self::decision('approve', 'Setujui', 'heroicon-o-check-circle', 'success', Discount::REQUESTED, PermissionName::DiscountsApprove),
                self::decision('reject', 'Tolak', 'heroicon-o-x-circle', 'danger', Discount::REQUESTED, PermissionName::DiscountsApprove),
                self::decision('revoke', 'Cabut', 'heroicon-o-arrow-uturn-left', 'gray', Discount::APPROVED, PermissionName::DiscountsApprove),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkDecision('approve', 'Setujui', 'heroicon-o-check-circle', 'success'),
                    self::bulkDecision('reject', 'Tolak', 'heroicon-o-x-circle', 'danger'),
                    self::bulkDecision('revoke', 'Cabut', 'heroicon-o-arrow-uturn-left', 'gray'),
                    BulkAction::make('bulkExcel')
                        ->label('Unduh Excel (yang dipilih)')
                        ->icon('heroicon-o-table-cells')
                        ->action(fn (Collection $records) => ReportDownloader::respond(
                            DiscountReportBuilder::build($records, ['Hanya baris yang dipilih']), 'excel'))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulkPdf')
                        ->label('Unduh PDF (yang dipilih)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(fn (Collection $records) => ReportDownloader::respond(
                            DiscountReportBuilder::build($records, ['Hanya baris yang dipilih']), 'pdf'))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /** Keputusan massal: centang beberapa pengajuan, lalu satu aksi. Tiap pengajuan diproses sendiri-sendiri. */
    private static function bulkDecision(string $name, string $label, string $icon, string $color): BulkAction
    {
        return BulkAction::make('bulk'.ucfirst($name))
            ->label("{$label} yang dipilih")
            ->icon($icon)
            ->color($color)
            ->visible(fn (): bool => (bool) auth()->user()?->can(PermissionName::DiscountsApprove->value))
            ->modalHeading("{$label} diskon yang dipilih")
            ->modalDescription($name === 'revoke'
                ? 'Hanya diskon berstatus Disetujui yang dicabut; yang lain dilewati.'
                : 'Hanya pengajuan berstatus Diajukan yang diproses; yang lain dilewati.')
            ->schema([
                Textarea::make('note')->label('Catatan (opsional, berlaku untuk semua)')->rows(2)->maxLength(500),
            ])
            ->action(function (Collection $records, array $data, $livewire) use ($name, $label): void {
                abort_unless(auth()->user()?->can(PermissionName::DiscountsApprove->value), 403);

                $service = app(DiscountService::class);
                $done = 0;
                $failed = [];

                foreach ($records->load('bill.student') as $record) {
                    try {
                        $service->{$name}($record, auth()->user(), $data['note'] ?? null);
                        $done++;
                    } catch (DomainException $e) {
                        $failed[] = "{$record->bill->student->name}: {$e->getMessage()}";
                    }
                }

                Notification::make()
                    ->color($failed === [] ? 'success' : 'warning')
                    ->title("{$label}: {$done} berhasil".($failed === [] ? '' : ', '.count($failed).' dilewati'))
                    ->body($failed === [] ? null : Str::limit(implode('; ', $failed), 300))
                    ->duration($failed === [] ? 5000 : 10000)
                    ->send();

                Nav::refresh($livewire);
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function decision(string $name, string $label, string $icon, string $color, string $onlyStatus, PermissionName $permission): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (Discount $record): bool => $record->status === $onlyStatus && (bool) auth()->user()?->can($permission->value))
            ->modalHeading("{$label} diskon")
            ->schema([
                Textarea::make('note')->label('Catatan (opsional)')->rows(2)->maxLength(500),
            ])
            ->action(function (Discount $record, array $data, $livewire) use ($name, $label, $permission): void {
                abort_unless(auth()->user()?->can($permission->value), 403);

                try {
                    app(DiscountService::class)->{$name}($record, auth()->user(), $data['note'] ?? null);
                } catch (DomainException $e) {
                    Notification::make()->danger()->title("Gagal: {$label}")->body($e->getMessage())->duration(8000)->send();

                    return;
                }

                Notification::make()->success()->title("Diskon: {$label} berhasil")->duration(5000)->send();

                Nav::refresh($livewire);
            });
    }

    public static function getPages(): array
    {
        return ['index' => ListDiscounts::route('/')];
    }
}
