<?php

namespace App\Filament\Pages;

use App\Enums\PermissionName;
use App\Exports\ReportDownloader;
use App\Exports\ReportTable;
use App\Models\CashBookOpening;
use App\Models\CashEntry;
use App\Services\CashBook;
use App\Services\CashEntryService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\RawJs;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use UnitEnum;

class BukuKas extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Buku Kas';

    protected static ?string $title = 'Buku Kas';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.buku-kas';

    public string $from = '';

    public string $until = '';

    public string $account = '';

    public static function canAccess(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->can(PermissionName::ExpensesView->value) || $u?->can(PermissionName::ExpensesManage->value));
    }

    public function canManage(): bool
    {
        return (bool) auth()->user()?->can(PermissionName::ExpensesManage->value);
    }

    public function mount(): void
    {
        $this->preset('month');
    }

    public function preset(string $key): void
    {
        $t = today(config('app.timezone'));

        [$a, $b] = match ($key) {
            'today' => [$t, $t],
            'last_month' => [$t->copy()->subMonthNoOverflow()->startOfMonth(), $t->copy()->subMonthNoOverflow()->endOfMonth()],
            'year' => [$t->copy()->startOfYear(), $t],
            default => [$t->copy()->startOfMonth(), $t],
        };

        $this->from = $a->toDateString();
        $this->until = $b->toDateString();
    }

    public function updatedFrom(): void
    {
        if ($this->until === '' || $this->until < $this->from) {
            $this->until = $this->from;
        }
    }

    public function updatedUntil(): void
    {
        if ($this->from === '' || $this->until < $this->from) {
            $this->from = $this->until;
        }
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function ledgers(): array
    {
        abort_unless(static::canAccess(), 403);

        $svc = app(CashBook::class);
        $a = Carbon::parse($this->from, config('app.timezone'));
        $b = Carbon::parse($this->until, config('app.timezone'));
        $accounts = $this->account !== '' ? [$this->account] : ['kas', 'bank'];

        return array_map(fn (string $acc): array => $svc->ledger($acc, $a, $b), $accounts);
    }

    private function moneyInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->required()
            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))->stripCharacters('.')->numeric()->minValue(1);
    }

    private function run(\Closure $fn, string $ok): void
    {
        abort_unless($this->canManage(), 403);

        try {
            $fn();
        } catch (DomainException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        unset($this->ledgers);
        Notification::make()->success()->title($ok)->send();
    }

    protected function getHeaderActions(): array
    {
        $today = today(config('app.timezone'));

        return [
            ActionGroup::make([
                Action::make('dlExcel')->label('Excel (.xlsx)')->icon('heroicon-o-table-cells')->action(fn () => $this->download('excel')),
                Action::make('dlPdf')->label('PDF (siap cetak)')->icon('heroicon-o-document-arrow-down')->action(fn () => $this->download('pdf')),
            ])->label('Unduh')->icon('heroicon-o-arrow-down-tray')->color('gray')->button(),

            Action::make('income')
                ->label('Pemasukan lain')
                ->icon('heroicon-o-arrow-down-on-square')
                ->color('success')
                ->visible(fn (): bool => $this->canManage())
                ->modalHeading('Catat pemasukan lain')
                ->modalDescription('Di luar pembayaran siswa, mis. dana BOS, donasi, bunga bank. Pembayaran siswa otomatis masuk dari Kasir.')
                ->schema([
                    DatePicker::make('entry_date')->label('Tanggal')->default($today)->maxDate($today)->required(),
                    TextInput::make('description')->label('Keterangan')->required()->maxLength(200),
                    $this->moneyInput('amount', 'Jumlah (Rp)'),
                    Select::make('account')->label('Masuk ke')->options(['kas' => 'Kas (tunai)', 'bank' => 'Bank'])->default('bank')->required(),
                    TextInput::make('party')->label('Dari (opsional)')->maxLength(120),
                    TextInput::make('reference_no')->label('No. bukti (opsional)')->maxLength(60),
                    FileUpload::make('proof')->label('Bukti (opsional)')->disk('local')->directory('expense-proofs')->visibility('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])->maxSize(4096),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(CashEntryService::class)->income($data, auth()->user()), 'Pemasukan dicatat')),

            Action::make('transfer')
                ->label('Setor / tarik tunai')
                ->icon('heroicon-o-arrows-right-left')
                ->color('info')
                ->visible(fn (): bool => $this->canManage())
                ->modalHeading('Mutasi antara kas dan bank')
                ->modalDescription('Dicatat sebagai dua baris berpasangan (keluar dari satu akun, masuk ke akun lain).')
                ->schema([
                    DatePicker::make('entry_date')->label('Tanggal')->default($today)->maxDate($today)->required(),
                    Select::make('from')->label('Arah')->options(['kas' => 'Setor tunai ke bank (kas → bank)', 'bank' => 'Tarik tunai dari bank (bank → kas)'])->default('kas')->required(),
                    $this->moneyInput('amount', 'Jumlah (Rp)'),
                    TextInput::make('note')->label('Catatan (opsional)')->maxLength(150),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(CashEntryService::class)->transfer((string) $data['from'], $data['amount'], $data['entry_date'], $data['note'] ?? null, auth()->user()), 'Mutasi dicatat')),

            Action::make('opening')
                ->label('Saldo awal')
                ->icon('heroicon-o-flag')
                ->color('gray')
                ->visible(fn (): bool => $this->canManage())
                ->modalHeading('Atur saldo awal buku kas')
                ->modalDescription('Saldo uang pada AWAL tanggal yang dipilih. Pergerakan sebelum tanggal itu tidak dihitung. Isi sekali saat mulai memakai buku kas; boleh diubah bila keliru.')
                ->fillForm(function (): array {
                    $o = CashBookOpening::query()->get()->keyBy('account');

                    return [
                        'as_of' => ($o['kas'] ?? $o['bank'] ?? null)?->as_of?->toDateString() ?? today(config('app.timezone'))->startOfMonth()->toDateString(),
                        'kas' => (string) ($o['kas']->amount ?? 0),
                        'bank' => (string) ($o['bank']->amount ?? 0),
                    ];
                })
                ->schema([
                    DatePicker::make('as_of')->label('Berlaku sejak awal tanggal')->required(),
                    TextInput::make('kas')->label('Saldo kas (tunai) Rp')->required()->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))->stripCharacters('.')->numeric()->minValue(0),
                    TextInput::make('bank')->label('Saldo bank Rp')->required()->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))->stripCharacters('.')->numeric()->minValue(0),
                ])
                ->action(fn (array $data) => $this->run(function () use ($data): void {
                    $svc = app(CashEntryService::class);
                    $svc->setOpening('kas', $data['kas'], $data['as_of'], auth()->user());
                    $svc->setOpening('bank', $data['bank'], $data['as_of'], auth()->user());
                }, 'Saldo awal disimpan')),
        ];
    }

    public function voidEntryAction(): Action
    {
        return Action::make('voidEntry')
            ->color('danger')
            ->modalHeading('Batalkan catatan ini?')
            ->modalDescription('Catatan tidak dihapus, hanya ditandai Batal dan tidak lagi dihitung. Untuk mutasi kas/bank, kedua barisnya dibatalkan bersama.')
            ->schema([Textarea::make('reason')->label('Alasan')->required()->maxLength(200)])
            ->action(fn (array $arguments, array $data) => $this->run(function () use ($arguments, $data): void {
                app(CashEntryService::class)->void(CashEntry::query()->findOrFail((int) $arguments['id']), auth()->user(), (string) $data['reason']);
            }, 'Catatan dibatalkan'));
    }

    public function download(string $format)
    {
        abort_unless(static::canAccess(), 403);

        $rows = [];
        $n = 0;

        foreach ($this->ledgers() as $l) {
            $rows[] = ['no' => '', 'date' => '', 'account' => $l['label'], 'desc' => 'SALDO AWAL', 'ref' => '', 'in' => 0, 'out' => 0, 'balance' => $l['opening']];

            foreach ($l['rows'] as $r) {
                $rows[] = ['no' => ++$n, 'date' => Carbon::parse($r['date'])->format('d/m/Y'), 'account' => $l['label'], 'desc' => $r['desc'], 'ref' => $r['ref'], 'in' => $r['in'], 'out' => $r['out'], 'balance' => $r['balance']];
            }
        }

        $a = Carbon::parse($this->from)->format('d/m/Y');
        $b = Carbon::parse($this->until)->format('d/m/Y');
        $sub = ["Periode {$a} s.d. {$b}", 'Masuk: pembayaran siswa (Sah), pemasukan lain, mutasi. Keluar: pengeluaran dan mutasi.'];

        foreach ($this->ledgers() as $l) {
            if (! $l['tracked']) {
                $sub[] = $l['label'].': saldo awal belum diatur / periode sebelum tanggal saldo awal — saldo dihitung dari 0';
            }
        }

        return ReportDownloader::respond(new ReportTable(
            title: 'Buku Kas',
            filename: 'buku-kas-'.now()->format('Ymd-His'),
            subtitle: $sub,
            columns: [
                ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 5],
                ['key' => 'date', 'label' => 'Tanggal', 'width' => 12],
                ['key' => 'account', 'label' => 'Akun', 'width' => 20],
                ['key' => 'desc', 'label' => 'Keterangan', 'width' => 48],
                ['key' => 'ref', 'label' => 'No. bukti', 'width' => 14],
                ['key' => 'in', 'label' => 'Masuk', 'type' => 'money', 'width' => 16],
                ['key' => 'out', 'label' => 'Keluar', 'type' => 'money', 'width' => 16],
                ['key' => 'balance', 'label' => 'Saldo', 'type' => 'money', 'width' => 16],
            ],
            rows: $rows,
            totalKeys: ['in', 'out'],
        ), $format);
    }
}
