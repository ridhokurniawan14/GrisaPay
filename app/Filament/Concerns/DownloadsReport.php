<?php

namespace App\Filament\Concerns;

use App\Exports\ReportDownloader;
use App\Exports\ReportTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tombol "Unduh" (Excel / PDF) di halaman daftar. Isinya mengikuti filter, tab, pencarian, dan urutan yang sedang aktif.
 * Halaman pemakai wajib mengimplementasikan buildReport() dan filterSummary().
 */
trait DownloadsReport
{
    /** @param list<string> $subtitle */
    abstract protected function buildReport(Collection $records, array $subtitle): ReportTable;

    /** @return list<string> Keterangan filter aktif untuk ditulis di bawah judul laporan. */
    abstract protected function filterSummary(): array;

    /** @return array<string, string> kode => nama jenis laporan; kosong berarti laporan tunggal. */
    protected function reportModes(): array
    {
        return [];
    }

    /** @return array<string, string> kode => penjelasan singkat (tampil di bawah pilihan jenis laporan). */
    protected function reportModeDescriptions(): array
    {
        return [];
    }

    protected function buildReportMode(string $mode, Collection $records, array $subtitle): ReportTable
    {
        return $this->buildReport($records, $subtitle);
    }

    protected function downloadActions(): Action|ActionGroup
    {
        if ($this->reportModes() !== []) {
            $modes = $this->reportModes();

            return Action::make('unduh')
                ->label('Unduh')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->modalHeading('Unduh laporan')
                ->modalDescription(fn (): string => implode(' · ', $this->filterSummary()))
                ->modalIcon('heroicon-o-arrow-down-tray')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Unduh')
                ->schema([
                    Select::make('mode')
                        ->label('Jenis laporan')
                        ->options($modes)
                        ->default(array_key_first($modes))
                        ->required()
                        ->native(false)
                        ->live()
                        ->helperText(fn (Get $get): ?string => $this->reportModeDescriptions()[$get('mode')] ?? null),
                    ToggleButtons::make('format')
                        ->label('Format')
                        ->options(['excel' => 'Excel (.xlsx)', 'pdf' => 'PDF (siap cetak)'])
                        ->icons(['excel' => 'heroicon-o-table-cells', 'pdf' => 'heroicon-o-document-arrow-down'])
                        ->default('excel')
                        ->inline()
                        ->required(),
                ])
                ->action(fn (array $data) => $this->downloadReport((string) $data['format'], (string) $data['mode']));
        }

        return ActionGroup::make([
            Action::make('downloadExcel')
                ->label('Excel (.xlsx)')
                ->icon('heroicon-o-table-cells')
                ->action(fn () => $this->downloadReport('excel')),
            Action::make('downloadPdf')
                ->label('PDF (siap cetak)')
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn () => $this->downloadReport('pdf')),
        ])
            ->label('Unduh')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->button();
    }

    protected function downloadReport(string $format, ?string $mode = null)
    {
        $records = $this->getFilteredSortedTableQuery()->get();

        $subtitle = $this->filterSummary();

        if (filled($this->tableSearch ?? null)) {
            $subtitle[] = 'Pencarian: "'.$this->tableSearch.'"';
        }

        return ReportDownloader::respond(
            $mode === null ? $this->buildReport($records, $subtitle) : $this->buildReportMode($mode, $records, $subtitle),
            $format,
        );
    }
}
