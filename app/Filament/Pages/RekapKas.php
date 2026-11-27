<?php

namespace App\Filament\Pages;

use App\Enums\PermissionName;
use App\Exports\CashRecapReport;
use App\Exports\GroupedReport;
use App\Exports\ReportDownloader;
use App\Models\Payment;
use App\Models\User;
use App\Services\CashRecap;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Support\Facades\Cache;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use UnitEnum;

class RekapKas extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Rekap Kas';

    protected static ?string $title = 'Rekap Kas';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.rekap-kas';

    public string $from = '';

    public string $until = '';

    public string $method = '';

    public string $cashier = '';

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can(PermissionName::ReportsDaily->value) || $u?->can(PermissionName::ReportsPeriodic->value));
    }

    public function mount(): void
    {
        $this->from = $this->until = today(config('app.timezone'))->toDateString();
    }

    /** Rekap berkala (rentang tanggal) hanya untuk yang punya izin berkala; rekap harian cukup satu tanggal. */
    public function canRange(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::ReportsPeriodic->value);
    }

    public function updatedFrom(): void
    {
        if (! $this->canRange() || $this->until === '' || $this->until < $this->from) {
            $this->until = $this->from;
        }
    }

    public function updatedUntil(): void
    {
        if (! $this->canRange() || $this->from === '' || $this->until < $this->from) {
            $this->from = $this->until;
        }
    }

    public function preset(string $key): void
    {
        abort_unless($this->canRange() || in_array($key, ['today', 'yesterday'], true), 403);

        $tz = config('app.timezone');
        $today = today($tz);

        [$a, $b] = match ($key) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'week' => [$today->copy()->subDays(6), $today],
            'month' => [$today->copy()->startOfMonth(), $today],
            'lastmonth' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'year' => [$today->copy()->startOfYear(), $today],
            default => [$today, $today],
        };

        $this->from = $a->toDateString();
        $this->until = $b->toDateString();
    }

    /** @return array<int, string> */
    #[Computed]
    public function cashiers(): array
    {
        return User::query()
            ->whereIn('id', Payment::query()->whereNotNull('created_by')->distinct()->pluck('created_by'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    #[Computed]
    public function recap(): array
    {
        [$a, $b] = CashRecap::range($this->from, $this->until);
        $payments = CashRecap::payments($a, $b, $this->method ?: null, $this->cashier !== '' ? (int) $this->cashier : null);

        return CashRecap::summarize($payments);
    }

    /** @return list<string> */
    private function periodLines(): array
    {
        [$a, $b] = CashRecap::range($this->from, $this->until);
        $fmt = fn (Carbon $d): string => $d->locale('id')->translatedFormat('d F Y');

        $lines = ['Periode: '.($a->isSameDay($b) ? $fmt($a) : $fmt($a).' s.d. '.$fmt($b))];

        if ($this->method !== '') {
            $lines[] = 'Metode: '.(Payment::METHODS[$this->method] ?? $this->method);
        }

        if ($this->cashier !== '') {
            $lines[] = 'Kasir: '.($this->cashiers()[(int) $this->cashier] ?? '-');
        }

        return $lines;
    }

    public function download(string $mode, string $format, array $options = [])
    {
        abort_unless(static::canAccess(), 403);

        [$a, $b] = CashRecap::range($this->from, $this->until);
        $payments = CashRecap::payments($a, $b, $this->method ?: null, $this->cashier !== '' ? (int) $this->cashier : null);
        $summary = CashRecap::summarize($payments);

        $report = CashRecapReport::build($mode, $payments, $summary, $this->periodLines(), $options);

        return $report instanceof GroupedReport
            ? $report->download($format)
            : ReportDownloader::respond($report, $format);
    }

    /** Modal daftar pembayaran di balik kotak/angka yang diklik (halaman tetap di Rekap Kas). */
    public function detailAction(): Action
    {
        return Action::make('detail')
            ->modalHeading(fn (array $arguments): string => $this->detailData((string) ($arguments['key'] ?? 'total'))['title'])
            ->modalContent(fn (array $arguments) => view('filament.pages.rekap-kas-detail', $this->detailData((string) ($arguments['key'] ?? 'total'))))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalWidth('7xl');
    }

    /** @var array<string, array<string, mixed>> */
    private array $detailMemo = [];

    private function detailData(string $key): array
    {
        abort_unless(static::canAccess(), 403);

        return $this->detailMemo[$key] ??= (function () use ($key): array {
            [$a, $b] = CashRecap::range($this->from, $this->until);
            $payments = CashRecap::payments($a, $b, $this->method ?: null, $this->cashier !== '' ? (int) $this->cashier : null);

            return CashRecap::detail($payments, $key);
        })();
    }

    protected function getHeaderActions(): array
    {
        $options = [];

        foreach (CashRecapReport::GROUPS as $group => $modes) {
            foreach ($modes as $mode) {
                $options[$group][$mode] = CashRecapReport::MODES[$mode];
            }
        }

        return [
            Action::make('unduh')
                ->label('Unduh')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->modalHeading('Unduh laporan')
                ->modalDescription(fn (): string => implode(' · ', $this->periodLines()))
                ->modalIcon('heroicon-o-arrow-down-tray')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Unduh')
                ->schema([
                    Select::make('mode')
                        ->label('Jenis laporan')
                        ->options($options)
                        ->default('buku')
                        ->required()
                        ->native(false)
                        ->live()
                        ->helperText(fn (Get $get): ?string => CashRecapReport::DESCRIPTIONS[$get('mode')] ?? null),
                    TextInput::make('komite')
                        ->label('Nama Ketua Komite (opsional)')
                        ->helperText('Dicetak di bawah tanda tangan. Kosongkan bila ingin ditulis tangan. Diingat untuk unduhan berikutnya.')
                        ->default(fn (): string => (string) Cache::get('grisapay.ketua_komite', ''))
                        ->maxLength(120)
                        ->visible(fn (Get $get): bool => $get('mode') === 'komite'),
                    ToggleButtons::make('format')
                        ->label('Format')
                        ->options(['excel' => 'Excel (.xlsx)', 'pdf' => 'PDF (siap cetak)'])
                        ->icons(['excel' => 'heroicon-o-table-cells', 'pdf' => 'heroicon-o-document-arrow-down'])
                        ->default('excel')
                        ->inline()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $komite = trim((string) ($data['komite'] ?? ''));

                    if ($data['mode'] === 'komite') {
                        Cache::forever('grisapay.ketua_komite', $komite);
                    }

                    return $this->download((string) $data['mode'], (string) $data['format'], ['komite' => $komite]);
                }),
        ];
    }
}
