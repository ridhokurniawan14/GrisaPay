<?php

namespace App\Filament\Resources\Expenses;

use App\Enums\PermissionName;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\CashEntry;
use App\Models\ExpenseCategory;
use App\Services\CashEntryService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class ExpenseResource extends Resource
{
    protected static ?string $model = CashEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Pengeluaran';

    protected static ?string $modelLabel = 'pengeluaran';

    protected static ?string $pluralModelLabel = 'Pengeluaran';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can(PermissionName::ExpensesView->value) || $u?->can(PermissionName::ExpensesManage->value));
    }

    public static function canManage(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::ExpensesManage->value);
    }

    public static function canCreate(): bool
    {
        return false; // dicatat lewat tombol "Catat pengeluaran"
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('kind', CashEntry::KELUAR)->with(['category', 'creator', 'voider']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('entry_date')->orderByDesc('id'))
            ->poll('30s')
            ->columns([
                TextColumn::make('entry_date')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('category.name')->label('Kategori')->badge()->color('gray'),
                TextColumn::make('description')->label('Keterangan')->searchable()->wrap()
                    ->description(fn (CashEntry $r): ?string => $r->party ? 'Kepada: '.$r->party : null),
                TextColumn::make('reference_no')->label('No. nota')->searchable()->toggleable(),
                TextColumn::make('account')->label('Dari')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'kas' ? 'Kas' : 'Bank')
                    ->color(fn (string $state): string => $state === 'kas' ? 'success' : 'info'),
                TextColumn::make('amount')->label('Jumlah')->alignEnd()->sortable()
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((int) $state, 0, ',', '.'))
                    ->extraAttributes(fn (CashEntry $r): array => $r->status === CashEntry::BATAL ? ['style' => 'text-decoration:line-through;opacity:.6'] : []),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $s): string => $s === CashEntry::SAH ? 'Sah' : 'Batal')
                    ->color(fn (string $s): string => $s === CashEntry::SAH ? 'success' : 'danger')
                    ->description(fn (CashEntry $r): ?string => $r->status === CashEntry::BATAL ? ($r->void_reason ?: null) : null),
                TextColumn::make('creator.name')->label('Dicatat oleh')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('period')
                    ->label('Periode')
                    ->schema([DatePicker::make('from')->label('Dari tanggal'), DatePicker::make('until')->label('Sampai tanggal')])
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('entry_date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('entry_date', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        $out = [];

                        if ($data['from'] ?? null) {
                            $out[] = 'Dari '.\Illuminate\Support\Carbon::parse($data['from'])->format('d/m/Y');
                        }

                        if ($data['until'] ?? null) {
                            $out[] = 'Sampai '.\Illuminate\Support\Carbon::parse($data['until'])->format('d/m/Y');
                        }

                        return $out;
                    }),
                SelectFilter::make('expense_category_id')->label('Kategori')
                    ->options(fn () => ExpenseCategory::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('account')->label('Dari akun')->options(['kas' => 'Kas', 'bank' => 'Bank']),
                SelectFilter::make('status')->label('Status')->options([CashEntry::SAH => 'Sah', CashEntry::BATAL => 'Batal'])->default(CashEntry::SAH),
            ])
            ->recordActions([
                Action::make('proof')
                    ->label('Bukti')
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->visible(fn (CashEntry $r): bool => filled($r->proof_path))
                    ->action(function (CashEntry $r) {
                        abort_unless(static::canAccess(), 403);

                        return Storage::disk('local')->download((string) $r->proof_path);
                    }),
                Action::make('void')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (CashEntry $r): bool => static::canManage() && $r->status === CashEntry::SAH)
                    ->modalHeading('Batalkan pengeluaran ini?')
                    ->modalDescription('Catatan tidak dihapus, hanya ditandai Batal dan tidak lagi dihitung di buku kas. Untuk koreksi, catat ulang pengeluaran yang benar.')
                    ->schema([Textarea::make('reason')->label('Alasan pembatalan')->required()->maxLength(200)])
                    ->action(function (CashEntry $r, array $data): void {
                        abort_unless(static::canManage(), 403);

                        try {
                            app(CashEntryService::class)->void($r, auth()->user(), (string) $data['reason']);
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title('Pengeluaran dibatalkan')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListExpenses::route('/')];
    }
}
