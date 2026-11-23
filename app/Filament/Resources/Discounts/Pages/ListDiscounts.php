<?php

namespace App\Filament\Resources\Discounts\Pages;

use App\Enums\PermissionName;
use App\Exports\DiscountReportBuilder;
use App\Exports\ReportTable;
use App\Filament\Concerns\DownloadsReport;
use App\Filament\Resources\Discounts\DiscountResource;
use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Discount;
use App\Models\DiscountType;
use App\Models\FeeCategory;
use App\Services\DiscountService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ListDiscounts extends ListRecords
{
    use DownloadsReport;

    protected static string $resource = DiscountResource::class;

    protected function buildReport(Collection $records, array $subtitle): ReportTable
    {
        return DiscountReportBuilder::build($records, $subtitle);
    }

    protected function filterSummary(): array
    {
        $tabs = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak & dicabut', 'semua' => 'Semua status'];
        $statuses = [Discount::REQUESTED => 'Diajukan', Discount::APPROVED => 'Disetujui', Discount::REJECTED => 'Ditolak', Discount::REVOKED => 'Dicabut'];
        $filters = $this->tableFilters ?? [];

        $lines = [];

        if ($year = AcademicYear::query()->find($filters['year']['value'] ?? null)) {
            $lines[] = "Tahun ajaran: {$year->code}";
        } else {
            $lines[] = 'Tahun ajaran: semua';
        }

        $lines[] = 'Tab: '.($tabs[$this->activeTab ?? 'menunggu'] ?? 'Semua status');

        if (filled($filters['status']['value'] ?? null)) {
            $lines[] = 'Status: '.($statuses[$filters['status']['value']] ?? $filters['status']['value']);
        }

        return $lines;
    }

    /** Tab status supaya bendahara langsung melihat yang menunggu keputusan. */
    public function getTabs(): array
    {
        $count = fn (string $status): int => Discount::query()->where('status', $status)->count();

        return [
            'menunggu' => Tab::make('Menunggu')
                ->badge($count(Discount::REQUESTED) ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Discount::REQUESTED)),
            'disetujui' => Tab::make('Disetujui')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Discount::APPROVED)),
            'ditolak' => Tab::make('Ditolak')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [Discount::REJECTED, Discount::REVOKED])),
            'semua' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'menunggu';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->downloadActions(),
            Action::make('request')
                ->label('Ajukan diskon')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => (bool) auth()->user()?->can(PermissionName::DiscountsRequest->value))
                ->modalHeading('Ajukan diskon')
                ->modalDescription('Diskon baru berlaku setelah disetujui bendahara.')
                ->modalWidth('2xl')
                ->schema([
                    Select::make('bill_ids')
                        ->label('Siswa (tagihan tahun ajaran aktif)')
                        ->helperText('Bisa memilih banyak siswa sekaligus. Ketik nama, NISN, atau nama kelas (mis. "X AKL 1") untuk mencari; pilihan sebelumnya tetap tersimpan.')
                        ->multiple()
                        ->searchable()
                        ->required()
                        ->live()
                        ->getSearchResultsUsing(function (string $search): array {
                            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

                            return Bill::query()
                                ->where('academic_year_id', AcademicYear::current()?->id)
                                ->where(fn ($q) => $q
                                    ->where('classroom_name', 'ilike', $like)
                                    ->orWhereHas('student', fn ($s) => $s->where('name', 'ilike', $like)->orWhere('nisn', 'like', $like)))
                                ->with('student')
                                ->orderBy('classroom_name')
                                ->limit(60)
                                ->get()
                                ->mapWithKeys(fn (Bill $b): array => [$b->id => "{$b->student->name} · {$b->classroom_name}"])
                                ->all();
                        })
                        ->getOptionLabelsUsing(fn (array $values): array => Bill::query()
                            ->with('student')
                            ->whereIn('id', $values)
                            ->get()
                            ->mapWithKeys(fn (Bill $b): array => [$b->id => "{$b->student->name} · {$b->classroom_name}"])
                            ->all()),
                    Select::make('discount_type_id')
                        ->label('Jenis diskon')
                        ->options(fn () => DiscountType::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $type = DiscountType::query()->find($state);

                            if ($type === null) {
                                return;
                            }

                            $set('scope', $type->default_scope);
                            $set('fee_category_id', $type->default_fee_category_id);
                            $set('mode', $type->default_mode);
                            $set('value', $type->default_value);
                        }),
                    Select::make('scope')
                        ->label('Diskon berlaku untuk')
                        ->options([
                            Discount::SCOPE_BILL => 'Seluruh tagihan',
                            Discount::SCOPE_CATEGORY => 'Satu kategori',
                            Discount::SCOPE_ITEM => 'Satu rincian',
                        ])
                        ->default(Discount::SCOPE_BILL)
                        ->required()
                        ->live(),
                    Select::make('fee_category_id')
                        ->label('Kategori')
                        ->options(fn () => FeeCategory::query()->where('code', '!=', FeeCategory::CARRYOVER)->orderBy('sort_order')->pluck('receipt_label', 'id')->all())
                        ->visible(fn (Get $get): bool => $get('scope') === Discount::SCOPE_CATEGORY)
                        ->required(fn (Get $get): bool => $get('scope') === Discount::SCOPE_CATEGORY),
                    Select::make('item_name')
                        ->label('Rincian')
                        ->options(fn (): array => BillItem::query()
                            ->whereHas('bill', fn ($q) => $q->where('academic_year_id', AcademicYear::current()?->id))
                            ->distinct()->orderBy('name')->pluck('name', 'name')->all())
                        ->searchable()
                        ->visible(fn (Get $get): bool => $get('scope') === Discount::SCOPE_ITEM)
                        ->required(fn (Get $get): bool => $get('scope') === Discount::SCOPE_ITEM)
                        ->helperText('Dicocokkan lewat nama rincian di tagihan tiap siswa. Siswa yang tidak punya rincian ini dilewati.'),
                    Select::make('mode')
                        ->label('Bentuk potongan')
                        ->options([Discount::MODE_PERCENT => 'Persen (%)', Discount::MODE_AMOUNT => 'Nominal (Rp)'])
                        ->default(Discount::MODE_PERCENT)
                        ->required()
                        ->live(),
                    TextInput::make('value')
                        ->label(fn (Get $get): string => $get('mode') === Discount::MODE_AMOUNT ? 'Nominal potongan' : 'Persentase potongan')
                        ->numeric()->integer()->minValue(1)
                        ->maxValue(fn (Get $get): ?int => $get('mode') === Discount::MODE_PERCENT ? 100 : null)
                        ->prefix(fn (Get $get): ?string => $get('mode') === Discount::MODE_AMOUNT ? 'Rp' : null)
                        ->suffix(fn (Get $get): ?string => $get('mode') === Discount::MODE_PERCENT ? '%' : null)
                        ->required(),
                    Textarea::make('reason')
                        ->label('Alasan / bukti')
                        ->helperText('Contoh: Juara 1 kelas semester ganjil, surat keterangan tidak mampu dari desa, akta kematian orang tua.')
                        ->required()->rows(3)->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->can(PermissionName::DiscountsRequest->value), 403);

                    try {
                        $result = app(DiscountService::class)->requestMany($data, auth()->user());
                    } catch (DomainException $e) {
                        Notification::make()->danger()->title('Pengajuan gagal')->body($e->getMessage())->duration(8000)->send();

                        return;
                    }

                    $skipped = $result['skipped'];
                    $body = $skipped === [] ? null : count($skipped).' dilewati: '.\Illuminate\Support\Str::limit(implode('; ', $skipped), 400);

                    Notification::make()
                        ->color($skipped === [] ? 'success' : 'warning')
                        ->title("{$result['created']} diskon diajukan, menunggu persetujuan bendahara")
                        ->body($body)
                        ->duration($skipped === [] ? 5000 : 10000)
                        ->send();
                }),
        ];
    }
}
