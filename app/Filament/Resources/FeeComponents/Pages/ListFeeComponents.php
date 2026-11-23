<?php

namespace App\Filament\Resources\FeeComponents\Pages;

use App\Enums\PermissionName;
use App\Filament\Resources\FeeComponents\FeeComponentResource;
use App\Models\AcademicYear;
use App\Models\FeeComponent;
use App\Services\BillGenerator;
use App\Support\TariffAudit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListFeeComponents extends ListRecords
{
    protected static string $resource = FeeComponentResource::class;

    protected function getHeaderActions(): array
    {
        $years = fn (): array => AcademicYear::query()->orderByDesc('start_date')->pluck('code', 'id')->all();

        return [
            Action::make('copyYear')
                ->label('Salin dari tahun lain')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->modalHeading('Salin tarif dari tahun ajaran lain')
                ->modalDescription('Rincian yang sudah ada di tahun tujuan (tingkat, kategori, dan nama sama) dilewati, tidak ditimpa.')
                ->schema([
                    Select::make('from')->label('Salin dari')->options($years)->required(),
                    Select::make('to')->label('Ke tahun ajaran')->options($years)
                        ->default(fn () => AcademicYear::current()?->id)->required()->different('from'),
                    TextInput::make('percent')
                        ->label('Naikkan nominal (%)')
                        ->numeric()->integer()->minValue(0)->maxValue(100)->default(0)->suffix('%')
                        ->helperText('0 = nominal sama persis dengan tahun asal.'),
                ])
                ->action(function (array $data): void {
                    $percent = (int) ($data['percent'] ?? 0);
                    $added = 0;

                    DB::transaction(function () use ($data, $percent, &$added): void {
                        foreach (FeeComponent::query()->where('academic_year_id', $data['from'])->get() as $source) {
                            $component = FeeComponent::query()->firstOrCreate(
                                [
                                    'academic_year_id' => $data['to'],
                                    'grade' => $source->grade,
                                    'fee_category_id' => $source->fee_category_id,
                                    'name' => $source->name,
                                ],
                                [
                                    'amount' => (int) round($source->amount * (100 + $percent) / 100),
                                    'sort_order' => $source->sort_order,
                                ],
                            );

                            $added += $component->wasRecentlyCreated ? 1 : 0;
                        }
                    });

                    TariffAudit::note('Tarif disalin antar tahun ajaran', [
                        'dari' => AcademicYear::find($data['from'])?->code,
                        'ke' => AcademicYear::find($data['to'])?->code,
                        'kenaikan' => "{$percent}%",
                        'ditambah' => $added,
                    ]);

                    Notification::make()->success()->title("{$added} rincian tarif disalin")->send();
                }),

            Action::make('generateBills')
                ->label('Buat tagihan siswa')
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->visible(fn (): bool => (bool) auth()->user()?->can(PermissionName::BillsManage->value))
                ->modalHeading('Buat tagihan dari tarif')
                ->modalDescription('Membuat tagihan untuk siswa aktif yang punya kelas. Tagihan yang sudah ada tidak diubah; hanya rincian baru yang ditambahkan.')
                ->schema([
                    Select::make('year')->label('Tahun ajaran')->options($years)
                        ->default(fn () => AcademicYear::current()?->id)->required(),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->can(PermissionName::BillsManage->value), 403);

                    $year = AcademicYear::findOrFail($data['year']);
                    $stats = app(BillGenerator::class)->generate($year);

                    TariffAudit::note('Tagihan siswa dibuat dari tarif', ['tahun_ajaran' => $year->code] + $stats);

                    $warn = $stats['students_without_tariff'] > 0;

                    Notification::make()
                        ->color($warn ? 'warning' : 'success')
                        ->title("Tagihan TA {$year->code}")
                        ->body("Tagihan baru {$stats['bills_created']} · Rincian ditambah {$stats['items_added']}"
                            .($warn ? " · {$stats['students_without_tariff']} siswa belum punya tarif di tingkatnya" : ''))
                        ->duration($warn ? 10000 : 5000)
                        ->send();
                }),

            CreateAction::make()
                ->label('Tambah tarif')
                ->after(fn (FeeComponent $record) => TariffAudit::created($record)),
        ];
    }
}
