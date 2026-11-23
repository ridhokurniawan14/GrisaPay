<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Enums\PermissionName;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\FeeCategory;
use App\Models\Payment;
use App\Models\PaymentVoid;
use App\Models\Student;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getTabs(): array
    {
        return [
            'hari-ini' => Tab::make('Hari ini')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('paid_at', today())),
            'menunggu' => Tab::make('Menunggu verifikasi')
                ->badge(Payment::query()->where('status', Payment::PENDING)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Payment::PENDING)),
            'void' => Tab::make('Void menunggu')
                ->badge(PaymentVoid::query()->where('status', PaymentVoid::REQUESTED)->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('voids', fn ($q) => $q->where('status', PaymentVoid::REQUESTED))),
            'semua' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'hari-ini';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('record')
                ->label('Catat pembayaran')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => (bool) auth()->user()?->can(PermissionName::PaymentsCreate->value))
                ->modalHeading('Catat pembayaran')
                ->modalDescription('Tunai langsung sah dan mendapat nomor kwitansi. Transfer dan QRIS menunggu verifikasi bendahara.')
                ->modalWidth('2xl')
                ->schema([
                    Select::make('student_id')
                        ->label('Siswa')
                        ->searchable()
                        ->required()
                        ->live()
                        ->getSearchResultsUsing(function (string $search): array {
                            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

                            return Student::query()
                                ->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('nisn', 'like', $like))
                                ->orderBy('name')
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn (Student $s): array => [$s->id => "{$s->name} · {$s->nisn}"])
                                ->all();
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => ($s = Student::query()->find($value)) ? "{$s->name} · {$s->nisn}" : null)
                        ->helperText(function (Get $get): ?string {
                            $student = $get('student_id') ? Student::query()->find($get('student_id')) : null;

                            if ($student === null) {
                                return 'Ketik nama atau NISN.';
                            }

                            $total = app(PaymentService::class)->outstandingTotal($student, $get('focus') ?: null);

                            return 'Sisa tagihan yang bisa dibayar: Rp '.number_format($total, 0, ',', '.');
                        }),
                    Select::make('focus')
                        ->label('Dibayarkan untuk')
                        ->options(fn (): array => ['' => 'Otomatis (tunggakan dulu, lalu tahun ini)', 'carry' => 'Hanya tunggakan sebelumnya']
                            + FeeCategory::query()->where('code', '!=', FeeCategory::CARRYOVER)->orderBy('sort_order')->get()
                                ->mapWithKeys(fn (FeeCategory $c): array => ['cat:'.$c->id => 'Hanya '.$c->receipt_label.' (tahun ajaran aktif)'])->all())
                        ->default('')
                        ->live(),
                    Select::make('method')
                        ->label('Metode')
                        ->options(Payment::METHODS)
                        ->default(Payment::CASH)
                        ->required()
                        ->live(),
                    TextInput::make('amount')
                        ->label('Jumlah dibayar')
                        ->numeric()->integer()->minValue(1)->prefix('Rp')->required(),
                    TextInput::make('reference')
                        ->label('No. referensi transfer / QRIS')
                        ->visible(fn (Get $get): bool => in_array($get('method'), [Payment::TRANSFER, Payment::QRIS], true))
                        ->maxLength(100),
                    TextInput::make('payer_name')->label('Nama penyetor')->maxLength(100),
                    Textarea::make('note')->label('Catatan')->rows(2)->maxLength(500),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->can(PermissionName::PaymentsCreate->value), 403);

                    PaymentResource::run(
                        fn () => app(PaymentService::class)->record($data, auth()->user()),
                        'Pembayaran dicatat',
                    );
                }),
        ];
    }
}
