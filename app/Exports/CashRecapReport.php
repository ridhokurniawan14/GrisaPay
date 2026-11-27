<?php

namespace App\Exports;

use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Menyusun laporan Excel/PDF rekap kas: ringkasan per hari, per kategori, atau rincian transaksi. */
final class CashRecapReport
{
    public const MODES = [
        'harian' => 'Ringkasan per hari',
        'kategori' => 'Per kategori',
        'item' => 'Per rincian tagihan',
        'tingkat' => 'Per tingkat kelas',
        'kelas' => 'Per kelas',
        'rincian' => 'Rincian transaksi',
        'buku' => 'Buku penerimaan harian — untuk Kepala Sekolah',
        'komite' => 'Laporan penerimaan harian — untuk Komite (tanpa tunggakan)',
        'buku_rinci' => 'Buku penerimaan per transaksi',
    ];

    /** Pengelompokan & penjelasan singkat untuk pilihan di jendela Unduh. */
    public const GROUPS = [
        'Buku penerimaan' => ['buku', 'komite', 'buku_rinci'],
        'Ringkasan' => ['harian', 'kategori', 'item', 'tingkat', 'kelas'],
        'Detail' => ['rincian'],
    ];

    public const DESCRIPTIONS = [
        'buku' => 'Satu baris per tanggal, total pemasukan tiap rincian tarif termasuk tunggakan. Tanda tangan: Kepala Sekolah dan Bendahara.',
        'komite' => 'Sama seperti buku harian, tetapi tanpa kolom tunggakan. Tanda tangan: Ketua Komite, Kepala Sekolah, Bendahara.',
        'buku_rinci' => 'Satu baris per pembayaran, kolom uang dipecah per rincian tarif.',
        'harian' => 'Jumlah transaksi dan total per hari, dipisah Tunai / Transfer / QRIS.',
        'kategori' => 'Total pemasukan per kategori tarif.',
        'item' => 'Total pemasukan per rincian tagihan.',
        'tingkat' => 'Pemasukan per hari menurut tingkat kelas siswa (X / XI / XII / Alumni).',
        'kelas' => 'Pemasukan per kelas.',
        'rincian' => 'Daftar lengkap transaksi beserta kasir dan rincian yang dibayar.',
    ];

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<string>  $subtitle
     */
    public static function build(string $mode, Collection $payments, array $summary, array $subtitle, array $options = []): ReportTable|GroupedReport
    {
        $stamp = now()->format('Ymd-His');

        $note = [];

        if ($summary['pending']['count'] > 0) {
            $note[] = 'Belum termasuk '.$summary['pending']['count'].' pembayaran menunggu verifikasi (Rp '.number_format($summary['pending']['total'], 0, ',', '.').')';
        }

        $subtitle = array_merge($subtitle, ['Hanya pembayaran berstatus Sah'], $note);

        return match ($mode) {
            'kategori' => self::byCategory($summary, $subtitle, $stamp),
            'item' => self::byItem($summary, $subtitle, $stamp),
            'tingkat' => self::byGrade($summary, $subtitle, $stamp),
            'kelas' => self::byClass($summary, $subtitle, $stamp),
            'rincian' => self::detail($payments, $subtitle, $stamp),
            'buku' => DailyLedgerReport::build($payments, $subtitle, $stamp),
            'komite' => DailyLedgerReport::build($payments, $subtitle, $stamp, true, (string) ($options['komite'] ?? '')),
            'buku_rinci' => ReceiptLedgerReport::build($payments, $subtitle, $stamp),
            default => self::byDay($summary, $subtitle, $stamp),
        };
    }

    private static function byDay(array $summary, array $subtitle, string $stamp): ReportTable
    {
        $rows = [];

        foreach ($summary['by_day'] as $i => $d) {
            $rows[] = [
                'no' => $i + 1,
                'date' => Carbon::parse($d['date'])->locale('id')->translatedFormat('l, d/m/Y'),
                'count' => $d['count'],
                'cash' => $d['cash'],
                'transfer' => $d['transfer'],
                'qris' => $d['qris'],
                'total' => $d['total'],
            ];
        }

        return new ReportTable(
            title: 'Rekap Kas per Hari',
            filename: 'rekap-kas-harian-'.$stamp,
            subtitle: $subtitle,
            columns: [
                ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 5],
                ['key' => 'date', 'label' => 'Tanggal', 'width' => 24],
                ['key' => 'count', 'label' => 'Transaksi', 'type' => 'int', 'width' => 11],
                ['key' => 'cash', 'label' => 'Tunai', 'type' => 'money', 'width' => 16],
                ['key' => 'transfer', 'label' => 'Transfer', 'type' => 'money', 'width' => 16],
                ['key' => 'qris', 'label' => 'QRIS', 'type' => 'money', 'width' => 16],
                ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'width' => 18],
            ],
            rows: $rows,
            totalKeys: ['count', 'cash', 'transfer', 'qris', 'total'],
        );
    }

    private static function byCategory(array $summary, array $subtitle, string $stamp): ReportTable
    {
        $rows = [];

        foreach ($summary['by_category'] as $i => $c) {
            $rows[] = ['no' => $i + 1, 'name' => $c['name'], 'count' => $c['count'], 'total' => $c['total']];
        }

        return new ReportTable(
            title: 'Rekap Kas per Kategori',
            filename: 'rekap-kas-kategori-'.$stamp,
            subtitle: $subtitle,
            columns: [
                ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 5],
                ['key' => 'name', 'label' => 'Kategori', 'width' => 30],
                ['key' => 'count', 'label' => 'Jumlah rincian terbayar', 'type' => 'int', 'width' => 22],
                ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'width' => 18],
            ],
            rows: $rows,
            totalKeys: ['count', 'total'],
        );
    }

    private static function byGrade(array $summary, array $subtitle, string $stamp): ReportTable
    {
        $rows = [];

        foreach ($summary['by_day'] as $i => $d) {
            $rows[] = [
                'no' => $i + 1,
                'date' => Carbon::parse($d['date'])->locale('id')->translatedFormat('l, d/m/Y'),
                'g10' => $d['g10'], 'g11' => $d['g11'], 'g12' => $d['g12'], 'g0' => $d['g0'],
                'total' => $d['total'],
            ];
        }

        return new ReportTable(
            title: 'Rekap Kas per Tingkat Kelas',
            filename: 'rekap-kas-tingkat-'.$stamp,
            subtitle: array_merge($subtitle, ['Tingkat dihitung dari kelas siswa pada tahun ajaran saat membayar']),
            columns: [
                ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 5],
                ['key' => 'date', 'label' => 'Tanggal', 'width' => 24],
                ['key' => 'g10', 'label' => 'Kelas X', 'type' => 'money', 'width' => 16],
                ['key' => 'g11', 'label' => 'Kelas XI', 'type' => 'money', 'width' => 16],
                ['key' => 'g12', 'label' => 'Kelas XII', 'type' => 'money', 'width' => 16],
                ['key' => 'g0', 'label' => 'Alumni / tanpa kelas', 'type' => 'money', 'width' => 18],
                ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'width' => 18],
            ],
            rows: $rows,
            totalKeys: ['g10', 'g11', 'g12', 'g0', 'total'],
        );
    }

    private static function byClass(array $summary, array $subtitle, string $stamp): ReportTable
    {
        $rows = [];

        foreach ($summary['by_class'] as $i => $c) {
            $rows[] = ['no' => $i + 1, 'label' => $c['label'], 'name' => $c['name'], 'students' => $c['students'], 'count' => $c['count'], 'total' => $c['total']];
        }

        return new ReportTable(
            title: 'Rekap Kas per Kelas',
            filename: 'rekap-kas-kelas-'.$stamp,
            subtitle: $subtitle,
            columns: [
                ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 5],
                ['key' => 'label', 'label' => 'Tingkat', 'width' => 24],
                ['key' => 'name', 'label' => 'Kelas', 'width' => 18],
                ['key' => 'students', 'label' => 'Siswa membayar', 'type' => 'int', 'width' => 16],
                ['key' => 'count', 'label' => 'Transaksi', 'type' => 'int', 'width' => 11],
                ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'width' => 18],
            ],
            rows: $rows,
            totalKeys: ['count', 'total'],
        );
    }

    private static function byItem(array $summary, array $subtitle, string $stamp): ReportTable
    {
        $rows = [];

        foreach ($summary['by_item'] as $i => $it) {
            $rows[] = ['no' => $i + 1, 'category' => $it['category'], 'name' => $it['name'], 'year' => $it['year'], 'count' => $it['count'], 'total' => $it['total']];
        }

        return new ReportTable(
            title: 'Rekap Kas per Rincian Tagihan',
            filename: 'rekap-kas-rincian-tagihan-'.$stamp,
            subtitle: $subtitle,
            columns: [
                ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 5],
                ['key' => 'category', 'label' => 'Kategori', 'width' => 18],
                ['key' => 'name', 'label' => 'Rincian', 'width' => 30],
                ['key' => 'year', 'label' => 'TA', 'width' => 11, 'align' => 'center'],
                ['key' => 'count', 'label' => 'Transaksi', 'type' => 'int', 'width' => 11],
                ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'width' => 18],
            ],
            rows: $rows,
            totalKeys: ['count', 'total'],
        );
    }

    /** @param Collection<int, Payment> $payments */
    private static function detail(Collection $payments, array $subtitle, string $stamp): ReportTable
    {
        $rows = [];

        foreach ($payments->where('status', Payment::PAID)->values() as $i => $p) {
            $rows[] = [
                'no' => $i + 1,
                'paid_at' => $p->paid_at->format('d/m/Y H:i'),
                'receipt_no' => $p->receipt_no,
                'student' => $p->student->name,
                'nisn' => $p->student->nisn,
                'method' => $p->methodText(),
                'amount' => (int) $p->amount,
                'for' => $p->allocations->map(fn ($a): string => (($a->billItem?->name ?? '-').' ('.number_format((int) $a->amount, 0, ',', '.').')'))->implode('; '),
                'creator' => $p->creator?->name,
                'verifier' => $p->verifier?->name,
            ];
        }

        return new ReportTable(
            title: 'Rincian Penerimaan Kas',
            filename: 'rekap-kas-rincian-'.$stamp,
            subtitle: array_merge($subtitle, [count($rows).' transaksi']),
            columns: [
                ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 5],
                ['key' => 'paid_at', 'label' => 'Waktu', 'width' => 17, 'align' => 'center'],
                ['key' => 'receipt_no', 'label' => 'No. kwitansi', 'width' => 17],
                ['key' => 'student', 'label' => 'Siswa', 'width' => 28],
                ['key' => 'nisn', 'label' => 'NISN', 'width' => 13, 'pdf' => false],
                ['key' => 'method', 'label' => 'Metode', 'width' => 10],
                ['key' => 'amount', 'label' => 'Jumlah', 'type' => 'money', 'width' => 16],
                ['key' => 'for', 'label' => 'Dibayarkan untuk (rincian)', 'width' => 46],
                ['key' => 'creator', 'label' => 'Dicatat oleh', 'width' => 18],
                ['key' => 'verifier', 'label' => 'Diverifikasi oleh', 'width' => 18, 'pdf' => false],
            ],
            rows: $rows,
            totalKeys: ['amount'],
        );
    }
}
