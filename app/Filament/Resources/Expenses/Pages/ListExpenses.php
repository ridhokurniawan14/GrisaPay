<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Exports\ReportTable;
use App\Filament\Concerns\DownloadsReport;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\CashEntry;
use App\Models\ExpenseCategory;
use App\Services\CashEntryService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Collection;

class ListExpenses extends ListRecords
{
    use DownloadsReport;

    protected static string $resource = ExpenseResource::class;

    public function getSubheading(): ?string
    {
        $sum = (int) $this->getFilteredTableQuery()->where('status', CashEntry::SAH)->sum('amount');

        return 'Total pengeluaran sah sesuai filter: Rp '.number_format($sum, 0, ',', '.');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->downloadActions(),
            Action::make('create')
                ->label('Catat pengeluaran')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => ExpenseResource::canManage())
                ->modalHeading('Catat pengeluaran')
                ->modalWidth('2xl')
                ->schema([
                    DatePicker::make('entry_date')->label('Tanggal')->default(today(config('app.timezone')))->maxDate(today(config('app.timezone')))->required(),
                    Select::make('expense_category_id')->label('Kategori')->required()->searchable()
                        ->options(fn () => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->createOptionForm([TextInput::make('name')->label('Nama kategori baru')->required()->maxLength(80)->unique('expense_categories', 'name')])
                        ->createOptionUsing(fn (array $data) => ExpenseCategory::create(['name' => trim($data['name'])])->getKey()),
                    TextInput::make('description')->label('Keterangan')->required()->maxLength(200)->placeholder('mis. Beli kertas HVS 5 rim'),
                    TextInput::make('amount')->label('Jumlah (Rp)')->required()
                        ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))->stripCharacters('.')->numeric()->minValue(1),
                    Select::make('account')->label('Dibayar dari')->options(['kas' => 'Kas (tunai)', 'bank' => 'Bank'])->default('kas')->required(),
                    TextInput::make('party')->label('Dibayarkan kepada (opsional)')->maxLength(120),
                    TextInput::make('reference_no')->label('No. nota / bukti (opsional)')->maxLength(60),
                    FileUpload::make('proof')->label('Foto / scan nota (opsional)')
                        ->disk('local')->directory('expense-proofs')->visibility('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])->maxSize(4096),
                ])
                ->action(function (array $data): void {
                    abort_unless(ExpenseResource::canManage(), 403);

                    try {
                        app(CashEntryService::class)->expense($data, auth()->user());
                    } catch (DomainException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title('Pengeluaran dicatat')->send();
                }),
        ];
    }

    protected function filterSummary(): array
    {
        return ['Pengeluaran sesuai filter pada halaman; hanya status Sah yang dijumlahkan'];
    }

    protected function buildReport(Collection $records, array $subtitle): ReportTable
    {
        $rows = [];

        foreach ($records->values() as $i => $r) {
            $rows[] = [
                'no' => $i + 1,
                'date' => $r->entry_date->format('d/m/Y'),
                'category' => (string) $r->category?->name,
                'desc' => $r->description.($r->party ? ' (kepada '.$r->party.')' : ''),
                'ref' => (string) $r->reference_no,
                'account' => $r->account === 'kas' ? 'Kas' : 'Bank',
                'status' => $r->status === CashEntry::SAH ? 'Sah' : 'Batal',
                'amount' => $r->status === CashEntry::SAH ? $r->amount : 0,
            ];
        }

        return new ReportTable(
            title: 'Laporan Pengeluaran',
            filename: 'pengeluaran-'.now()->format('Ymd-His'),
            subtitle: $subtitle,
            columns: [
                ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 5],
                ['key' => 'date', 'label' => 'Tanggal', 'width' => 12],
                ['key' => 'category', 'label' => 'Kategori', 'width' => 24],
                ['key' => 'desc', 'label' => 'Keterangan', 'width' => 44],
                ['key' => 'ref', 'label' => 'No. nota', 'width' => 14],
                ['key' => 'account', 'label' => 'Dari', 'width' => 8],
                ['key' => 'status', 'label' => 'Status', 'width' => 9],
                ['key' => 'amount', 'label' => 'Jumlah', 'type' => 'money', 'width' => 16],
            ],
            rows: $rows,
            totalKeys: ['amount'],
        );
    }
}
