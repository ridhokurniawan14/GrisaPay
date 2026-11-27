<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Enums\PermissionName;
use App\Exports\BillReportBuilder;
use App\Exports\ReportTable;
use App\Filament\Concerns\DownloadsReport;
use App\Filament\Resources\Bills\BillResource;
use App\Models\AcademicYear;
use App\Services\ArrearImporter;
use App\Services\ArrearTemplate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;

class ListBills extends ListRecords
{
    use DownloadsReport;

    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->downloadActions(),
            ActionGroup::make([
                Action::make('arrearTemplate')
                    ->label('Unduh template Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn() => ArrearTemplate::download()),
                Action::make('importArrears')
                    ->label('Impor dari Excel / CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalHeading('Impor tunggakan / saldo awal siswa')
                    ->modalDescription('Untuk tunggakan siswa aktif maupun alumni. Kolom: NISN, Tahun Ajaran, Kategori (opsional), Keterangan (opsional), Jumlah. Aman diulang: baris yang sama persis dilewati.')
                    ->modalSubmitActionLabel('Jalankan')
                    ->schema([
                        FileUpload::make('file')
                            ->label('Berkas (.xlsx / .csv)')
                            ->required()
                            ->disk('local')
                            ->directory('imports')
                            ->visibility('private')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv',
                                'application/csv',
                                'text/plain',
                                'application/vnd.ms-excel',
                            ])
                            ->storeFileNamesIn('original_name')
                            ->maxSize(10240),
                        Toggle::make('commit')
                            ->label('Simpan ke database')
                            ->helperText('Matikan untuk simulasi: hasil dihitung lengkap tetapi tidak ada yang tersimpan. Disarankan simulasi dulu.')
                            ->default(false),
                    ])
                    ->action(function (array $data): void {
                        abort_unless(auth()->user()?->can(PermissionName::BillsManage->value), 403);
                        set_time_limit(300);

                        $file = is_array($data['file']) ? reset($data['file']) : $data['file'];
                        $commit = (bool) ($data['commit'] ?? false);

                        try {
                            $report = app(ArrearImporter::class)->import(
                                Storage::disk('local')->path($file),
                                (string) ($data['original_name'] ?? basename($file)),
                                auth()->user(),
                                dryRun: ! $commit,
                            );
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Berkas tidak dapat diproses')
                                ->body(Str::limit($e->getMessage(), 250))->duration(12000)->send();

                            return;
                        } finally {
                            Storage::disk('local')->delete($file);
                        }

                        $errors = $report['errors'];
                        $body = "Baris baru {$report['created']} · Dilewati (sudah ada) {$report['skipped']} · Bermasalah " . count($errors)
                            . ' · Total Rp ' . number_format($report['total_amount'], 0, ',', '.');

                        if ($errors !== []) {
                            $body .= ' · ' . Str::limit(implode('; ', array_slice($errors, 0, 3)), 300);
                        }

                        Notification::make()
                            ->color($errors === [] ? 'success' : 'warning')
                            ->title($commit ? 'Impor selesai' : 'Simulasi selesai (tidak ada data tersimpan)')
                            ->body($body)
                            ->duration(14000)
                            ->send();
                    }),
            ])
                ->label('Tunggakan awal')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('gray')
                ->button()
                ->visible(fn(): bool => (bool) auth()->user()?->can(PermissionName::BillsManage->value)),
        ];
    }

    protected function reportModes(): array
    {
        return [
            'ringkas' => 'Ringkasan per siswa',
            'rincian' => 'Rincian per item tagihan',
        ];
    }

    protected function buildReportMode(string $mode, Collection $records, array $subtitle): ReportTable
    {
        return $mode === 'rincian'
            ? BillReportBuilder::buildDetail($records, $subtitle)
            : BillReportBuilder::build($records, $subtitle);
    }

    protected function buildReport(Collection $records, array $subtitle): ReportTable
    {
        return BillReportBuilder::build($records, $subtitle);
    }

    protected function filterSummary(): array
    {
        $filters = $this->tableFilters ?? [];
        $lines = [];

        $year = AcademicYear::query()->find($filters['academic_year_id']['value'] ?? null);
        $lines[] = 'Tahun ajaran: ' . ($year?->code ?? 'semua');

        if (filled($filters['grade']['value'] ?? null)) {
            $lines[] = 'Tingkat: Kelas ' . $filters['grade']['value'];
        }

        if (filled($filters['classroom_name']['value'] ?? null)) {
            $lines[] = 'Kelas: ' . $filters['classroom_name']['value'];
        }

        return $lines;
    }
    protected function reportModeDescriptions(): array
    {
        return [
            'ringkas' => 'Satu baris per siswa: total tagihan, diskon, terbayar, dan sisa.',
            'rincian' => 'Satu baris per item tagihan untuk setiap siswa.',
        ];
    }
}
