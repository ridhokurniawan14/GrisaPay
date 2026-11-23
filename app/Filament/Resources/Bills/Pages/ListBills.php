<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Exports\BillReportBuilder;
use App\Exports\ReportTable;
use App\Filament\Concerns\DownloadsReport;
use App\Filament\Resources\Bills\BillResource;
use App\Models\AcademicYear;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;

class ListBills extends ListRecords
{
    use DownloadsReport;

    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->downloadActions()];
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
        $lines[] = 'Tahun ajaran: '.($year?->code ?? 'semua');

        if (filled($filters['grade']['value'] ?? null)) {
            $lines[] = 'Tingkat: Kelas '.$filters['grade']['value'];
        }

        if (filled($filters['classroom_name']['value'] ?? null)) {
            $lines[] = 'Kelas: '.$filters['classroom_name']['value'];
        }

        return $lines;
    }
}
