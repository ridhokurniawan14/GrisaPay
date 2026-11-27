<?php

namespace App\Exports;

use App\Models\FeeCategory;
use App\Models\InstitutionSetting;
use App\Models\FeeComponent;
use App\Models\Payment;
use App\Services\CashRecap;
use Illuminate\Support\Collection;

/**
 * Buku penerimaan HARIAN (untuk Kepala Sekolah: ttd KS + Bendahara) atau versi KOMITE ($komite = true:
 * tanpa kolom & nilai tunggakan, ttd Ketua Komite + KS + Bendahara): satu baris per tanggal, uang dijumlah per hari menurut rincian tarif.
 * Susunan kolom dinamis dari tarif yang ada:
 *  - Tunggakan Alumni / XI / XII (menurut kelas siswa saat ini; X hanya bila ada datanya)
 *  - Tiap kategori tarif: kategori berisi 1 rincian = satu kolom; berisi 2+ rincian = header kategori digabung
 *    dengan rincian di bawahnya. Kolom tarif tahun ajaran aktif selalu tampil (kosong bila tak ada pemasukan).
 */
final class DailyLedgerReport
{
    private const ARREAR_LABELS = [0 => 'Tunggakan Alumni', 10 => 'Tunggakan Kelas X', 11 => 'Tunggakan Kelas XI', 12 => 'Tunggakan Kelas XII'];

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<string>  $subtitle
     */
    public static function build(Collection $payments, array $subtitle, string $stamp, bool $komite = false, string $komiteName = ''): GroupedReport
    {
        $sah = $payments->where('status', Payment::PAID)->values();
        $place = CashRecap::placement($sah);

        $isArrear = fn (Payment $p, $a): bool => ($a->billItem?->category?->code === FeeCategory::CARRYOVER)
            || ($a->billItem?->bill && (int) $a->billItem->bill->academic_year_id !== (int) $p->academic_year_id);

        $keyOf = fn (int $catId, string $name): string => 'f'.$catId.'_'.md5(mb_strtolower(trim($name)));

        // Kolom tunggakan
        $arrearGrades = [0 => true, 11 => true, 12 => true];

        foreach ($sah as $p) {
            foreach ($p->allocations as $a) {
                if ($isArrear($p, $a)) {
                    $arrearGrades[$place[$p->id]['grade']] = true;
                }
            }
        }

        // Kolom tarif: tarif tahun-tahun yang terlibat + tahun ajaran aktif + nama yang muncul di data.
        $years = $sah->pluck('academic_year_id')->unique()->filter()->all();

        if (($current = \App\Models\AcademicYear::current()) !== null) {
            $years[] = $current->id;
        }

        $cats = FeeCategory::query()->get()->keyBy('id');
        $tariff = []; // key => [cat, name, order]

        $add = function (int $catId, string $name, int $sort) use (&$tariff, $cats, $keyOf): void {
            $key = $keyOf($catId, $name);
            $catSort = (int) ($cats->get($catId)?->sort_order ?? 999);

            if (! isset($tariff[$key])) {
                $tariff[$key] = ['cat' => $catId, 'name' => trim($name), 'order' => [$catSort, $catId, $sort, mb_strtolower(trim($name))]];
            } else {
                $tariff[$key]['order'][2] = min($tariff[$key]['order'][2], $sort);
            }
        };

        FeeComponent::query()->whereIn('academic_year_id', array_unique($years))
            ->where('fee_category_id', '!=', $cats->firstWhere('code', FeeCategory::CARRYOVER)?->id ?? 0)
            ->get()
            ->each(fn (FeeComponent $c) => $add((int) $c->fee_category_id, $c->name, (int) $c->sort_order));

        foreach ($sah as $p) {
            foreach ($p->allocations as $a) {
                if (! $isArrear($p, $a) && $a->billItem) {
                    $add((int) $a->billItem->fee_category_id, (string) $a->billItem->name, (int) $a->billItem->sort_order);
                }
            }
        }

        uasort($tariff, fn (array $x, array $y): int => $x['order'] <=> $y['order']);

        // Susun grup
        $groups = [];

        foreach ($komite ? [] : [0, 10, 11, 12] as $g) {
            if (isset($arrearGrades[$g])) {
                $groups[] = ['label' => self::ARREAR_LABELS[$g], 'columns' => [['key' => 'arrear_'.$g, 'label' => self::ARREAR_LABELS[$g], 'width' => 14]]];
            }
        }

        $byCat = [];

        foreach ($tariff as $key => $t) {
            $byCat[$t['cat']][] = ['key' => $key, 'label' => $t['name'], 'width' => 14];
        }

        foreach ($byCat as $catId => $columns) {
            $label = (string) ($cats->get($catId)?->receipt_label ?? 'Lainnya');
            $groups[] = ['label' => $label, 'columns' => $columns];
        }

        $known = [];

        foreach ($groups as $g) {
            foreach ($g['columns'] as $c) {
                $known[$c['key']] = true;
            }
        }

        // Baris per tanggal
        $days = [];

        foreach ($sah as $p) {
            $parts = [];
            $allocated = 0;
            $skipped = 0; // tunggakan yang tidak ditampilkan pada laporan komite

            foreach ($p->allocations as $a) {
                $amount = (int) $a->amount;
                $allocated += $amount;
                $arrear = $isArrear($p, $a);

                if ($komite && $arrear) {
                    $skipped += $amount;

                    continue;
                }

                $key = $arrear
                    ? 'arrear_'.$place[$p->id]['grade']
                    : $keyOf((int) ($a->billItem?->fee_category_id ?? 0), (string) ($a->billItem?->name ?? ''));

                if (! isset($known[$key])) {
                    $key = 'other';
                }

                $parts[$key] = ($parts[$key] ?? 0) + $amount;
            }

            $parts['other'] = ($parts['other'] ?? 0) + ((int) $p->amount - $allocated);
            $paidHere = (int) $p->amount - $skipped;

            if ($komite && $paidHere <= 0) {
                continue; // pembayaran yang seluruhnya tunggakan tidak masuk laporan komite
            }

            $date = $p->paid_at->format('Y-m-d');
            $days[$date] ??= ['date' => $p->paid_at->format('d/m/Y'), 'count' => 0, 'students' => [], 'total' => 0, 'other' => 0];
            $days[$date]['count']++;
            $days[$date]['students'][$p->student_id] = true;
            $days[$date]['total'] += $paidHere;

            foreach ($parts as $key => $amount) {
                $days[$date][$key] = ($days[$date][$key] ?? 0) + $amount;
            }
        }

        ksort($days);

        $rows = [];

        foreach (array_values($days) as $i => $d) {
            $d['no'] = $i + 1;
            $d['note'] = $d['count'].' transaksi · '.count($d['students']).' siswa';
            unset($d['count'], $d['students']);
            $rows[] = $d;
        }

        if (array_sum(array_column($rows, 'other')) !== 0) {
            $groups[] = ['label' => 'Lainnya', 'columns' => [['key' => 'other', 'label' => 'Lainnya', 'width' => 12]]];
        }

        if ($komite) {
            $subtitle[] = 'Tidak termasuk tunggakan';
        }

        return new GroupedReport(
            title: $komite ? 'Laporan Penerimaan untuk Komite' : 'Buku Penerimaan Harian',
            filename: ($komite ? 'laporan-komite-' : 'buku-penerimaan-harian-').$stamp,
            subtitle: $subtitle,
            lead: [
                ['key' => 'date', 'label' => 'Tanggal', 'type' => 'text', 'width' => 12, 'align' => 'center'],
                ['key' => 'note', 'label' => 'Keterangan', 'type' => 'text', 'width' => 24],
                ['key' => 'total', 'label' => 'Jumlah', 'type' => 'money', 'width' => 15],
            ],
            groups: $groups,
            rows: $rows,
            signers: self::signers($komite, $komiteName),
        );
    }

    /** @return list<array{role: string, name: string, nip: string}> */
    private static function signers(bool $komite, string $komiteName): array
    {
        try {
            $s = InstitutionSetting::current();
        } catch (\Throwable) {
            $s = null;
        }

        $ks = ['role' => 'Kepala Sekolah', 'name' => (string) ($s?->principal_name ?? ''), 'nip' => (string) ($s?->principal_nip ?? '')];
        $bendahara = ['role' => 'Bendahara', 'name' => (string) ($s?->treasurer_name ?? ''), 'nip' => (string) ($s?->treasurer_nip ?? '')];

        return $komite
            ? [['role' => 'Ketua Komite', 'name' => trim($komiteName), 'nip' => ''], $ks, $bendahara]
            : [$ks, $bendahara];
    }
}
