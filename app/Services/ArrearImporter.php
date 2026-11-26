<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ArrearImport;
use App\Models\DiscountAllocation;
use App\Models\FeeCategory;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Impor tunggakan / saldo awal siswa (termasuk alumni) dari Excel atau CSV.
 * Tiap baris menjadi item tagihan di tahun ajaran asalnya, sehingga ikut alokasi "tertua dulu", diskon, dan laporan.
 * Aman diulang: baris yang sama persis dilewati. Satu berkas = satu paket yang bisa dibatalkan.
 */
final class ArrearImporter
{
    /** @return list<list<mixed>> */
    public function readSheet(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();

        return array_values($sheet->toArray(null, true, true, false));
    }

    /**
     * @return array{created: int, skipped: int, errors: list<string>, total_amount: int, import_id: ?int}
     */
    public function import(string $path, string $filename, User $by, bool $dryRun): array
    {
        $parsed = ArrearRowParser::parse($this->readSheet($path));
        $errors = $parsed['errors'];

        $students = Student::query()->whereIn('nisn', array_unique(array_column($parsed['rows'], 'nisn')))->get()->keyBy('nisn');
        $years = AcademicYear::query()->get()->keyBy('code');
        $categories = FeeCategory::query()->get();
        $carryCategory = $categories->firstWhere('code', FeeCategory::CARRYOVER);

        if ($carryCategory === null) {
            throw new DomainException('Kategori Tunggakan belum ada. Jalankan FeeCategorySeeder.');
        }

        $created = 0;
        $skipped = 0;
        $total = 0;
        $createdBills = [];

        DB::beginTransaction();

        try {
            $import = ArrearImport::query()->create(['filename' => $filename, 'imported_by' => $by->id]);

            foreach ($parsed['rows'] as $row) {
                $student = $students->get($row['nisn']);
                $year = $years->get($row['year']);

                if ($student === null) {
                    $errors[] = "Baris {$row['line']}: NISN {$row['nisn']} tidak ada di data siswa";

                    continue;
                }

                if ($year === null) {
                    $errors[] = "Baris {$row['line']}: tahun ajaran {$row['year']} belum ada (tambahkan di GrisaID lalu sinkronkan)";

                    continue;
                }

                $category = $row['category'] === null
                    ? $carryCategory
                    : $categories->first(fn (FeeCategory $c): bool => mb_strtolower($c->receipt_label) === mb_strtolower($row['category']) || $c->code === mb_strtolower($row['category']));

                if ($category === null) {
                    $errors[] = "Baris {$row['line']}: kategori \"{$row['category']}\" tidak dikenal";

                    continue;
                }

                $bill = Bill::query()->where('student_id', $student->id)->where('academic_year_id', $year->id)->first();

                if ($bill === null) {
                    $enrollment = StudentEnrollment::query()
                        ->where('student_id', $student->id)->where('academic_year_id', $year->id)
                        ->with('classroom')->first();

                    $bill = Bill::query()->create([
                        'student_id' => $student->id,
                        'academic_year_id' => $year->id,
                        'grade' => $enrollment?->classroom?->grade ?? 0,
                        'classroom_name' => $enrollment?->classroom?->name ?? '-',
                    ]);
                    $createdBills[] = $bill->id;
                }

                $name = "Tunggakan TA {$year->code}".($row['note'] !== null ? " - {$row['note']}" : '');

                $exists = BillItem::query()
                    ->where('bill_id', $bill->id)->where('fee_category_id', $category->id)
                    ->where('name', $name)->where('amount', $row['amount'])
                    ->whereNotNull('arrear_import_id')
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                BillItem::query()->create([
                    'bill_id' => $bill->id,
                    'fee_category_id' => $category->id,
                    'fee_component_id' => null,
                    'arrear_import_id' => $import->id,
                    'name' => $name,
                    'amount' => $row['amount'],
                    'sort_order' => 900,
                ]);

                $created++;
                $total += $row['amount'];
            }

            $import->update(['items_count' => $created, 'total_amount' => $total, 'created_bill_ids' => $createdBills]);

            if ($created === 0) {
                $import->delete(); // berkas tanpa baris baru tidak perlu muncul di riwayat
                $importId = null;
            } else {
                $importId = $import->id;
            }

            if ($dryRun) {
                DB::rollBack();
                $importId = null;
            } else {
                DB::commit();

                if ($importId !== null) {
                    activity('tagihan')->causedBy($by)->withProperties([
                        'berkas' => $filename, 'item' => $created, 'total' => 'Rp '.number_format($total, 0, ',', '.'),
                    ])->log('Impor tunggakan / saldo awal');
                }
            }
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors, 'total_amount' => $total, 'import_id' => $importId];
    }

    /** Membatalkan satu paket impor. Ditolak bila ada item yang sudah dibayar atau didiskon. */
    public function undo(ArrearImport $import, User $by): void
    {
        DB::transaction(function () use ($import, $by): void {
            $locked = ArrearImport::query()->lockForUpdate()->findOrFail($import->id);

            if ($locked->status !== ArrearImport::ACTIVE) {
                throw new DomainException('Impor ini sudah dibatalkan.');
            }

            $itemIds = BillItem::query()->where('arrear_import_id', $locked->id)->pluck('id');

            $paid = PaymentAllocation::query()->whereIn('bill_item_id', $itemIds)->exists();
            $discounted = DiscountAllocation::query()->whereIn('bill_item_id', $itemIds)->exists()
                || BillItem::query()->whereIn('id', $itemIds)->where('discount_amount', '>', 0)->exists();

            if ($paid || $discounted) {
                throw new DomainException('Tidak bisa dibatalkan: sebagian tunggakan ini sudah dibayar atau didiskon.');
            }

            BillItem::query()->whereIn('id', $itemIds)->delete();

            // Tagihan yang dibuat oleh impor ini dan kini kosong ikut dihapus.
            foreach ($locked->created_bill_ids ?? [] as $billId) {
                if (! BillItem::query()->where('bill_id', $billId)->exists()) {
                    Bill::query()->whereKey($billId)->delete();
                }
            }

            $locked->update(['status' => ArrearImport::UNDONE, 'undone_by' => $by->id, 'undone_at' => now()]);

            activity('tagihan')->causedBy($by)->withProperties(['berkas' => $locked->filename, 'item' => $itemIds->count()])
                ->log('Impor tunggakan dibatalkan');
        });
    }
}
