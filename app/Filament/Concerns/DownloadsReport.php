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

    protected function downloadActions(): ActionGroup
    {
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

    protected function downloadReport(string $format)
    {
        $records = $this->getFilteredSortedTableQuery()->get();

        $subtitle = $this->filterSummary();

        if (filled($this->tableSearch ?? null)) {
            $subtitle[] = 'Pencarian: "'.$this->tableSearch.'"';
        }

        return ReportDownloader::respond($this->buildReport($records, $subtitle), $format);
    }
}
