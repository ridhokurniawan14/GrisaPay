<?php

namespace App\Filament\Pages;

use App\Enums\PermissionName;
use App\Services\DataReset;
use App\Services\DatabaseBackup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class Backups extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Cadangan Data';

    protected static ?string $title = 'Cadangan Data';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.backups';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::SettingsManage->value);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $svc = app(DatabaseBackup::class);

        return ['status' => $svc->check(), 'backups' => $svc->list(), 'dir' => $svc->dir()];
    }

    private static function passwordField(): TextInput
    {
        return TextInput::make('password')
            ->label('Kata sandi Anda (konfirmasi)')
            ->password()
            ->required()
            ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                if (! Hash::check((string) $value, (string) auth()->user()->password)) {
                    $fail('Kata sandi salah.');
                }
            });
    }

    private function logIt(string $message, array $props = []): void
    {
        activity('cadangan')->causedBy(auth()->user())->withProperties($props)->log($message);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Buat cadangan sekarang')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    try {
                        $r = app(DatabaseBackup::class)->create('manual');
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Cadangan gagal')->body($e->getMessage())->persistent()->send();

                        return;
                    }

                    $this->logIt('Membuat cadangan database', ['file' => $r['name']]);
                    Notification::make()->success()->title('Cadangan dibuat')->body($r['name'].' ('.number_format($r['size'] / 1048576, 2).' MB)')->send();
                }),
            Action::make('reset')
                ->label('Reset data transaksi')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->modalHeading('Reset data transaksi')
                ->modalDescription('Menghapus data transaksi secara permanen. Cadangan otomatis dibuat lebih dulu. Siswa, kelas, tahun ajaran, tarif, dan pengguna TIDAK dihapus.')
                ->modalSubmitActionLabel('Hapus permanen')
                ->schema([
                    Radio::make('mode')
                        ->label('Yang direset')
                        ->options([
                            DataReset::MODE_PAYMENTS => 'Pembayaran & kas saja — tagihan tetap, sisa tagihan kembali penuh',
                            DataReset::MODE_ALL => 'Pembayaran + tagihan (termasuk tunggakan awal & riwayat impor) — mulai dari nol',
                        ])
                        ->default(DataReset::MODE_PAYMENTS)
                        ->required()
                        ->live(),
                    Toggle::make('discounts')->label('Hapus juga data diskon')->default(false)
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('mode') === DataReset::MODE_ALL),
                    TextInput::make('confirm')->label('Ketik RESET untuk melanjutkan')->required()->in(['RESET'])->validationMessages(['in' => 'Ketik persis: RESET']),
                    self::passwordField(),
                ])
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);

                    $reset = app(DataReset::class);
                    $mode = (string) $data['mode'];
                    $discounts = (bool) ($data['discounts'] ?? false);

                    try {
                        $reset->plan($mode, $discounts);
                        $backup = app(DatabaseBackup::class)->create('sebelum-reset');
                        $done = $reset->run($mode, $discounts);
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Reset gagal')->body($e->getMessage())->persistent()->send();

                        return;
                    }

                    $this->logIt('Reset data transaksi', ['mode' => $mode, 'tabel' => $done, 'cadangan' => $backup['name']]);
                    Notification::make()->success()->title('Reset selesai')
                        ->body(count($done).' tabel dikosongkan. Cadangan sebelum reset: '.$backup['name'])->persistent()->send();
                }),
        ];
    }

    public function downloadAction(): Action
    {
        return Action::make('download')
            ->action(function (array $arguments) {
                abort_unless(static::canAccess(), 403);
                $this->logIt('Mengunduh cadangan', ['file' => $arguments['file'] ?? '']);

                return response()->download(app(DatabaseBackup::class)->path((string) $arguments['file']));
            });
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->requiresConfirmation()
            ->modalHeading('Hapus cadangan ini?')
            ->color('danger')
            ->action(function (array $arguments): void {
                abort_unless(static::canAccess(), 403);
                app(DatabaseBackup::class)->delete((string) $arguments['file']);
                $this->logIt('Menghapus cadangan', ['file' => $arguments['file']]);
                Notification::make()->success()->title('Cadangan dihapus')->send();
            });
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')
            ->color('warning')
            ->modalHeading('Pulihkan database dari cadangan ini?')
            ->modalDescription('SEMUA data saat ini diganti dengan isi cadangan (data setelah waktu cadangan hilang). Cadangan otomatis "sebelum-restore" dibuat lebih dulu. Anda mungkin perlu login ulang.')
            ->modalSubmitActionLabel('Pulihkan')
            ->schema([
                TextInput::make('confirm')->label('Ketik PULIHKAN untuk melanjutkan')->required()->in(['PULIHKAN'])->validationMessages(['in' => 'Ketik persis: PULIHKAN']),
                self::passwordField(),
            ])
            ->action(function (array $arguments): void {
                abort_unless(static::canAccess(), 403);

                try {
                    app(DatabaseBackup::class)->restore((string) $arguments['file']);
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('Pemulihan gagal')->body($e->getMessage())->persistent()->send();

                    return;
                }

                Notification::make()->success()->title('Database dipulihkan')->body('Dari '.$arguments['file'].'. Jalankan "php artisan migrate" bila cadangan berasal dari versi lama.')->persistent()->send();
            });
    }
}
