<?php

namespace App\Filament\Concerns;

use App\Exports\ReportDownloader;
use App\Exports\ReportTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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

    protected function buildReportMode(string $mode, Collection $records, array $subtitle): ReportTable
    {
        return $this->buildReport($records, $subtitle);
    }

    protected function downloadActions(): ActionGroup
    {
        if ($this->reportModes() !== []) {
            $items = [];

            foreach ($this->reportModes() as $mode => $label) {
                $items[] = Action::make("dl_{$mode}_xlsx")->label("{$label} — Excel")->icon('heroicon-o-table-cells')
                    ->action(fn () => $this->downloadReport('excel', $mode));
                $items[] = Action::make("dl_{$mode}_pdf")->label("{$label} — PDF")->icon('heroicon-o-document-arrow-down')
                    ->action(fn () => $this->downloadReport('pdf', $mode));
            }

            return ActionGroup::make($items)->label('Unduh')->icon('heroicon-o-arrow-down-tray')->color('gray')->button();
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
