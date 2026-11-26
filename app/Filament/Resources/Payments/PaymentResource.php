<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PermissionName;
use App\Services\PaymentPrintService;
use App\Support\Nav;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use App\Models\PaymentVoid;
use App\Services\PaymentService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static ?string $modelLabel = 'pembayaran';

    protected static ?string $pluralModelLabel = 'Pembayaran';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::PaymentsView->value);
    }

    /** Angka di menu: pembayaran menunggu verifikasi + pengajuan void menunggu persetujuan. */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! ($user?->can(PermissionName::PaymentsVerify->value) || $user?->can(PermissionName::PaymentsVoidApprove->value))) {
            return null;
        }

        $count = Payment::query()->where('status', Payment::PENDING)->count()
            + PaymentVoid::query()->where('status', PaymentVoid::REQUESTED)->count();

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
            ->modifyQueryUsing(fn ($query, $livewire) => $query
                ->with(['student', 'creator', 'verifier', 'voids', 'prints.printer'])
                ->when(
                    property_exists($livewire, 'studentId') && filled($livewire->studentId),
                    fn ($q) => $q->where('student_id', (int) $livewire->studentId),
                ))
            ->defaultSort('paid_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('receipt_no')->label('No. kwitansi')->placeholder('(belum ada)')->searchable(),
                TextColumn::make('paid_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('student.name')->label('Siswa')->searchable(),
                TextColumn::make('student.nisn')->label('NISN')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('method')->label('Metode')->badge()
                    ->formatStateUsing(fn (string $state): string => Payment::METHODS[$state] ?? $state),
                TextColumn::make('amount')->label('Jumlah')->alignEnd()->sortable()
                    ->formatStateUsing(fn ($state): string => 'Rp '.number_format((int) $state, 0, ',', '.')),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (Payment $record): string => match (true) {
                        $record->status === Payment::PAID && $record->pendingVoid() !== null => 'warning',
                        $record->status === Payment::PAID => 'success',
                        $record->status === Payment::PENDING => 'warning',
                        $record->status === Payment::REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state, Payment $record): string => match (true) {
                        $record->status === Payment::PAID && $record->pendingVoid() !== null => 'Sah · void diajukan',
                        $record->status === Payment::PAID => 'Sah',
                        $record->status === Payment::PENDING => 'Menunggu verifikasi',
                        $record->status === Payment::REJECTED => 'Ditolak',
                        default => 'Void',
                    }),
                TextColumn::make('prints_count')
                    ->label('Dicetak')
                    ->badge()
                    ->getStateUsing(fn (Payment $record): int => $record->prints->count())
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Belum' : "{$state}×")
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'danger',
                        $state >= 2 => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (Payment $record): string => $record->prints->isEmpty()
                        ? 'Belum pernah dicetak'
                        : $record->prints->map(fn ($p): string => $p->label().' · '.($p->printer?->name ?? '-').' · '.$p->printed_at->format('d/m/Y H:i'))->implode(' | '))
                    ->alignCenter(),
                TextColumn::make('reference')->label('Referensi')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payer_name')->label('Penyetor')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')->label('Dicatat oleh')->toggleable(),
                TextColumn::make('verifier.name')->label('Diverifikasi oleh')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('note')->label('Catatan')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')->label('Metode')->options(Payment::METHODS),
                SelectFilter::make('status')->label('Status')->options([
                    Payment::PENDING => 'Menunggu verifikasi', Payment::PAID => 'Sah',
                    Payment::REJECTED => 'Ditolak', Payment::VOID => 'Void',
                ]),
                Filter::make('paid_range')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal'),
                        DatePicker::make('until')->label('Sampai tanggal'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('paid_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('paid_at', '<=', $d))),
            ])
            ->recordActions([
                self::detailAction(),
                self::reprintAction(),
                self::verifyAction(),
                self::rejectAction(),
                self::requestVoidAction(),
                self::approveVoidAction(),
                self::rejectVoidAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkVerify')
                        ->label('Verifikasi yang dipilih')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (): bool => (bool) auth()->user()?->can(PermissionName::PaymentsVerify->value))
                        ->requiresConfirmation()
                        ->action(function (Collection $records, $livewire): void {
                            abort_unless(auth()->user()?->can(PermissionName::PaymentsVerify->value), 403);

                            $service = app(PaymentService::class);
                            $done = 0;
                            $failed = [];

                            foreach ($records->load('student') as $record) {
                                try {
                                    $service->verify($record, auth()->user());
                                    $done++;
                                } catch (DomainException $e) {
                                    $failed[] = "{$record->student->name}: {$e->getMessage()}";
                                }
                            }

                            Notification::make()
                                ->color($failed === [] ? 'success' : 'warning')
                                ->title("Verifikasi: {$done} berhasil".($failed === [] ? '' : ', '.count($failed).' dilewati'))
                                ->body($failed === [] ? null : Str::limit(implode('; ', $failed), 300))
                                ->duration($failed === [] ? 5000 : 10000)
                                ->send();

                            Nav::refresh($livewire);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    private static function detailAction(): Action
    {
        return Action::make('detail')
            ->label('Rincian')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading('Rincian pembayaran')
            ->modalWidth('3xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn (Payment $record) => view('filament.payment-detail', [
                'payment' => $record->load([
                    'student', 'creator', 'verifier', 'prints.printer', 'voids.requester', 'voids.decider',
                    'allocations.billItem.category', 'allocations.billItem.bill.academicYear',
                ]),
            ]));
    }

    private static function reprintAction(): Action
    {
        return Action::make('reprint')
            ->label(fn (Payment $record): string => $record->prints->isEmpty() ? 'Cetak struk' : 'Cetak ulang')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (Payment $record): bool => $record->status === Payment::PAID && (bool) auth()->user()?->can(PermissionName::PaymentsPrint->value))
            ->requiresConfirmation()
            ->modalHeading(fn (Payment $record): string => $record->prints->isEmpty() ? 'Cetak struk' : 'Cetak ulang struk')
            ->modalDescription(fn (Payment $record): string => $record->prints->isEmpty()
                ? 'Struk akan dicetak sebagai cetakan asli.'
                : 'Struk ini sudah dicetak '.$record->prints->count().'×. Cetakan berikutnya bertuliskan "CETAK ULANG KE-'.$record->prints->count().'" dan tercatat di log.')
            ->action(function (Payment $record, $livewire): void {
                abort_unless(auth()->user()?->can(PermissionName::PaymentsPrint->value), 403);

                try {
                    $print = app(PaymentPrintService::class)->record($record, auth()->user());
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Tidak bisa mencetak')->body($e->getMessage())->duration(8000)->send();

                    return;
                }

                $url = URL::temporarySignedRoute('receipt.show', now()->addMinutes(10), ['print' => $print->id]);

                // Tab baru. Bila pop-up diblokir browser, buka di tab yang sama.
                $livewire->js(sprintf('(function(){ var u = %s; var w = window.open(u, "_blank"); if (!w) { window.location.href = u; } })();', json_encode($url)));
            });
    }

    private static function verifyAction(): Action
    {
        return Action::make('verify')
            ->label('Verifikasi')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Payment $record): bool => $record->status === Payment::PENDING && (bool) auth()->user()?->can(PermissionName::PaymentsVerify->value))
            ->requiresConfirmation()
            ->modalHeading('Verifikasi pembayaran')
            ->modalDescription('Pastikan uang sudah benar-benar masuk. Setelah diverifikasi, pembayaran dibagi ke tagihan dan nomor kwitansi diberikan.')
            ->action(function (Payment $record, $livewire): void {
                abort_unless(auth()->user()?->can(PermissionName::PaymentsVerify->value), 403);

                if (self::run(fn () => app(PaymentService::class)->verify($record, auth()->user()), 'Pembayaran diverifikasi')) {
                    Nav::refresh($livewire);
                }
            });
    }

    private static function rejectAction(): Action
    {
        return Action::make('rejectPayment')
            ->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Payment $record): bool => $record->status === Payment::PENDING && (bool) auth()->user()?->can(PermissionName::PaymentsVerify->value))
            ->modalHeading('Tolak pembayaran')
            ->schema([Textarea::make('reason')->label('Alasan penolakan')->required()->rows(2)->maxLength(500)])
            ->action(function (Payment $record, array $data, $livewire): void {
                abort_unless(auth()->user()?->can(PermissionName::PaymentsVerify->value), 403);

                if (self::run(fn () => app(PaymentService::class)->reject($record, auth()->user(), $data['reason']), 'Pembayaran ditolak')) {
                    Nav::refresh($livewire);
                }
            });
    }

    private static function requestVoidAction(): Action
    {
        return Action::make('requestVoid')
            ->label('Ajukan void')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (Payment $record): bool => $record->status === Payment::PAID
                && $record->pendingVoid() === null
                && (bool) auth()->user()?->can(PermissionName::PaymentsVoidRequest->value))
            ->modalHeading('Ajukan void pembayaran')
            ->modalDescription('Void membatalkan pembayaran dan mengembalikan tagihan. Berlaku setelah disetujui bendahara. Nomor kwitansi tidak dipakai ulang.')
            ->schema([Textarea::make('reason')->label('Alasan void')->required()->rows(3)->maxLength(500)])
            ->action(function (Payment $record, array $data, $livewire): void {
                abort_unless(auth()->user()?->can(PermissionName::PaymentsVoidRequest->value), 403);

                if (self::run(fn () => app(PaymentService::class)->requestVoid($record, auth()->user(), $data['reason']), 'Void diajukan, menunggu persetujuan bendahara')) {
                    Nav::refresh($livewire);
                }
            });
    }

    private static function approveVoidAction(): Action
    {
        return Action::make('approveVoid')
            ->label('Setujui void')
            ->icon('heroicon-o-check-circle')
            ->color('danger')
            ->visible(fn (Payment $record): bool => $record->pendingVoid() !== null && (bool) auth()->user()?->can(PermissionName::PaymentsVoidApprove->value))
            ->modalHeading('Setujui void pembayaran')
            ->modalDescription(fn (Payment $record): string => 'Alasan pengaju: '.($record->pendingVoid()?->reason ?? '-'))
            ->schema([Textarea::make('note')->label('Catatan (opsional)')->rows(2)->maxLength(500)])
            ->action(function (Payment $record, array $data, $livewire): void {
                abort_unless(auth()->user()?->can(PermissionName::PaymentsVoidApprove->value), 403);

                if (self::run(fn () => app(PaymentService::class)->approveVoid($record->pendingVoid(), auth()->user(), $data['note'] ?? null), 'Void disetujui, pembayaran dibatalkan')) {
                    Nav::refresh($livewire);
                }
            });
    }

    private static function rejectVoidAction(): Action
    {
        return Action::make('rejectVoid')
            ->label('Tolak void')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->visible(fn (Payment $record): bool => $record->pendingVoid() !== null && (bool) auth()->user()?->can(PermissionName::PaymentsVoidApprove->value))
            ->modalHeading('Tolak pengajuan void')
            ->schema([Textarea::make('note')->label('Catatan (opsional)')->rows(2)->maxLength(500)])
            ->action(function (Payment $record, array $data, $livewire): void {
                abort_unless(auth()->user()?->can(PermissionName::PaymentsVoidApprove->value), 403);

                if (self::run(fn () => app(PaymentService::class)->rejectVoid($record->pendingVoid(), auth()->user(), $data['note'] ?? null), 'Pengajuan void ditolak')) {
                    Nav::refresh($livewire);
                }
            });
    }

    /** Menjalankan aksi layanan; kegagalan aturan bisnis tampil sebagai notifikasi, bukan error halaman. */
    public static function run(callable $callback, string $successMessage): bool
    {
        try {
            $callback();
        } catch (DomainException $e) {
            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->duration(8000)->send();

            return false;
        }

        Notification::make()->success()->title($successMessage)->duration(5000)->send();

        return true;
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
