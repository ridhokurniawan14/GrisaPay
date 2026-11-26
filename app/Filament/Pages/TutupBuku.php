<?php

namespace App\Filament\Pages;

use App\Enums\PermissionName;
use App\Models\AcademicYear;
use App\Models\YearClosing;
use App\Services\YearClosingService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class TutupBuku extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Tutup Buku';

    protected static ?string $title = 'Tutup Buku Tahun Ajaran';

    protected static ?int $navigationSort = 85;

    protected string $view = 'filament.pages.tutup-buku';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::SettingsManage->value);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $svc = app(YearClosingService::class);
        $closings = YearClosing::query()->with('closer')->get()->keyBy('academic_year_id');

        $years = AcademicYear::query()->orderByDesc('start_date')->get()->map(function (AcademicYear $y) use ($svc, $closings): array {
            $closing = $closings->get($y->id);
            $closed = $closing?->closed_at !== null;

            return [
                'model' => $y,
                'closed' => $closed,
                'closing' => $closing,
                'check' => $closed ? null : $svc->checklist($y),
                'summary' => $closed ? ($closing->snapshot ?? $svc->summary($y)) : null,
                'live' => $closed ? $svc->summary($y) : null,
            ];
        });

        // Tutup buku berurutan: hanya tahun TERTUA yang belum ditutup (bukan aktif, punya tagihan) yang boleh ditutup.
        $oldest = $years->filter(fn (array $r): bool => ! $r['closed'] && ! $r['model']->is_active && ($r['check']['summary']['bills'] ?? 0) > 0)
            ->sortBy(fn (array $r) => $r['model']->start_date)->first();
        $closableId = $oldest ? $oldest['model']->id : null;

        return ['years' => $years, 'closableId' => $closableId];
    }

    public function closeAction(): Action
    {
        return Action::make('close')
            ->label('Tutup buku')
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->modalHeading('Tutup buku tahun ajaran?')
            ->modalDescription('Tarif, tagihan, dan diskon tahun ini dikunci. Pembayaran tunggakan tetap bisa diterima. Bisa dibuka kembali oleh admin dengan alasan.')
            ->schema([
                Checkbox::make('backup')->label('Saya sudah membuat cadangan data (Pengaturan → Cadangan Data)')->accepted()->validationMessages(['accepted' => 'Buat cadangan dulu sebelum tutup buku.']),
                Checkbox::make('cash')->label('Saldo kas & bank di Buku Kas sudah dicocokkan')->accepted()->validationMessages(['accepted' => 'Cocokkan saldo kas dulu.']),
                Textarea::make('note')->label('Catatan (opsional)')->rows(2),
            ])
            ->action(function (array $data, array $arguments): void {
                try {
                    $year = AcademicYear::query()->findOrFail($arguments['year']);
                    app(YearClosingService::class)->close($year, auth()->user(), $data['note'] ?? null);
                    Notification::make()->success()->title("Tahun ajaran {$year->name} ditutup buku")->send();
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Gagal menutup buku')->body($e->getMessage())->send();
                }
            });
    }

    public function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label('Buka kembali')
            ->icon('heroicon-o-lock-open')
            ->color('gray')
            ->modalHeading('Buka kembali tutup buku?')
            ->schema([Textarea::make('reason')->label('Alasan (wajib, tercatat di log)')->required()->minLength(5)->rows(2)])
            ->action(function (array $data, array $arguments): void {
                try {
                    $year = AcademicYear::query()->findOrFail($arguments['year']);
                    app(YearClosingService::class)->reopen($year, auth()->user(), $data['reason']);
                    Notification::make()->success()->title("Tutup buku {$year->name} dibuka kembali")->send();
                } catch (DomainException $e) {
                    Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                }
            });
    }
}
