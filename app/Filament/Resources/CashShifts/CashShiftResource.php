<?php

namespace App\Filament\Resources\CashShifts;

use App\Enums\PermissionName;
use App\Filament\Resources\CashShifts\Pages\ListCashShifts;
use App\Models\CashShift;
use App\Models\User;
use App\Services\CashShiftService;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CashShiftResource extends Resource
{
    protected static ?string $model = CashShift::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Shift Kas';

    protected static ?string $modelLabel = 'shift kas';

    protected static ?string $pluralModelLabel = 'Shift Kas';

    protected static ?int $navigationSort = 7;

    private static function rp(?int $n): string
    {
        return $n === null ? '-' : 'Rp ' . number_format($n, 0, ',', '.');
    }

    public static function canReview(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::CashSessionsReview->value);
    }

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can(PermissionName::CashSessionsManage->value) || $u?->can(PermissionName::CashSessionsReview->value));
    }

    public static function canCreate(): bool
    {
        return false; // shift dibuka lewat halaman Kasir
    }

    public static function getNavigationBadge(): ?string
    {
        if (! self::canReview()) {
            return null;
        }

        $n = CashShift::query()->where('status', CashShift::CLOSED)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Kasir hanya melihat shift miliknya; peninjau (bendahara) melihat semua.
        return self::canReview() ? $query : $query->where('user_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('opened_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('user.name')->label('Kasir')->searchable(),
                TextColumn::make('opened_at')->label('Dibuka')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('closed_at')->label('Ditutup')->dateTime('d M Y H:i')->placeholder('masih terbuka')->sortable(),
                TextColumn::make('opening_cash')->label('Modal awal')->alignEnd()->formatStateUsing(fn($state): string => self::rp((int) $state)),
                TextColumn::make('expected')->label('Tunai seharusnya')->alignEnd()
                    ->state(fn(CashShift $r): string => self::rp($r->expectedCash())),
                TextColumn::make('counted_cash')->label('Tunai dihitung')->alignEnd()
                    ->formatStateUsing(fn($state): string => self::rp($state === null ? null : (int) $state)),
                TextColumn::make('difference')->label('Selisih')->alignEnd()->badge()
                    ->state(fn(CashShift $r): ?int => $r->difference)
                    ->color(fn($state): string => $state === null ? 'gray' : ((int) $state === 0 ? 'success' : ((int) $state < 0 ? 'danger' : 'warning')))
                    ->formatStateUsing(fn($state): string => $state === null ? '-' : ((int) $state === 0 ? 'Cocok' : ((int) $state < 0 ? 'Kurang ' : 'Lebih ') . self::rp(abs((int) $state)))),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn(string $state): string => match ($state) {
                        CashShift::OPEN => 'info',
                        CashShift::CLOSED => 'warning',
                        default => 'success'
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        CashShift::OPEN => 'Berjalan',
                        CashShift::CLOSED => 'Menunggu tinjauan',
                        default => 'Diterima'
                    }),
                TextColumn::make('reviewer.name')->label('Ditinjau oleh')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')->label('Kasir')
                    ->options(fn() => User::query()->whereIn('id', CashShift::query()->select('user_id'))->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn(): bool => self::canReview()),
                SelectFilter::make('status')->label('Status')->options([
                    CashShift::OPEN => 'Berjalan',
                    CashShift::CLOSED => 'Menunggu tinjauan',
                    CashShift::REVIEWED => 'Diterima',
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('compare')
                        ->label('Bandingkan shift')
                        ->icon('heroicon-o-scale')
                        ->visible(fn(): bool => self::canReview())
                        ->modalHeading('Perbandingan shift')
                        ->modalContent(fn($livewire) => view('filament.cash-shift-compare', self::compareData($livewire->getSelectedTableRecords())))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->modalWidth('7xl')
                        ->action(fn() => null)
                        ->deselectRecordsAfterCompletion(false),
                ]),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Rincian')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn(CashShift $record): string => 'Shift ' . ($record->user?->name ?? '-') . ' · ' . $record->opened_at->format('d/m/Y H:i') . ' WIB')
                    ->modalContent(function (CashShift $record) {
                        $payments = $record->payments()->with(['student'])->orderBy('paid_at')->get();

                        return view('filament.cash-shift-detail', [
                            'shift' => $record->load(['user', 'reviewer']),
                            'payments' => $payments,
                            'place' => \App\Services\CashRecap::placement($payments),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('6xl'),
                Action::make('review')
                    ->label('Terima & tinjau')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn(CashShift $record): bool => $record->status === CashShift::CLOSED && self::canReview())
                    ->modalHeading('Terima setoran shift ini?')
                    ->modalDescription(fn(CashShift $record): string => 'Tunai seharusnya ' . self::rp($record->expectedCash()) . ', dihitung ' . self::rp($record->counted_cash) . '. Selisih: ' . self::rp((int) $record->difference) . '.')
                    ->schema([Textarea::make('note')->label('Catatan bendahara (opsional)')->rows(2)])
                    ->action(function (CashShift $record, array $data, $livewire): void {
                        try {
                            app(CashShiftService::class)->review($record, auth()->user(), $data['note'] ?? null);
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title('Shift diterima')->duration(4000)->send();
                        Nav::refresh($livewire);
                    }),
            ]);
    }

    /**
     * Data perbandingan beberapa shift: per shift & gabungan (metode, tingkat, kelas, setoran).
     *
     * @param  \Illuminate\Support\Collection<int, CashShift>  $shifts
     * @return array<string, mixed>
     */
    public static function compareData($shifts): array
    {
        $shifts = $shifts->load('user')->sortBy('opened_at')->values();
        $payments = \App\Models\Payment::query()->whereIn('cash_shift_id', $shifts->pluck('id'))->where('status', \App\Models\Payment::PAID)->get();
        $place = \App\Services\CashRecap::placement($payments);

        $stat = function ($set) use ($place): array {
            $by = fn(string $m): int => (int) $set->where('method', $m)->sum('amount');
            $grades = [];
            foreach (array_keys(\App\Services\CashRecap::GRADE_LABELS) as $g) {
                $grades[$g] = (int) $set->filter(fn($p): bool => $place[$p->id]['grade'] === $g)->sum('amount');
            }
            $classes = $set->groupBy(fn($p): string => $place[$p->id]['class'])->map(fn($x): int => (int) $x->sum('amount'))->all();

            return [
                'cash' => $by(\App\Models\Payment::CASH),
                'transfer' => $by(\App\Models\Payment::TRANSFER),
                'qris' => $by(\App\Models\Payment::QRIS),
                'total' => (int) $set->sum('amount'),
                'count' => $set->count(),
                'grades' => $grades,
                'classes' => $classes,
            ];
        };

        $cols = $shifts->map(fn(CashShift $sh): array => $stat($payments->where('cash_shift_id', $sh->id)) + [
            'label' => ($sh->user?->name ?? '-'),
            'when' => $sh->opened_at->format('d/m H:i') . ($sh->closed_at ? '–' . $sh->closed_at->format('H:i') : ' (berjalan)'),
            'opening' => (int) $sh->opening_cash,
            'expected' => $sh->expectedCash(),
            'counted' => $sh->counted_cash,
            'diff' => $sh->difference,
        ])->all();

        $all = $stat($payments) + ['label' => 'Gabungan', 'when' => count($cols) . ' shift', 'opening' => array_sum(array_column($cols, 'opening')), 'expected' => array_sum(array_column($cols, 'expected')), 'counted' => null, 'diff' => null];

        $classNames = collect($cols)->flatMap(fn($c) => array_keys($c['classes']))->unique()
            ->sortBy(fn($n): string => ($n === 'Alumni / tanpa kelas' ? '9' : '1') . $n)->values()->all();

        return ['cols' => $cols, 'all' => $all, 'classNames' => $classNames];
    }

    public static function getPages(): array
    {
        return ['index' => ListCashShifts::route('/')];
    }
}
