@php
    $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $limit = 500;
    $shown = array_slice($rows, 0, $limit);
    $stateClass = fn (string $s): string => match ($s) { 'Sah' => 'rkd-ok', 'Menunggu verifikasi' => 'rkd-warn', default => 'rkd-bad' };
@endphp

<style>
    .rkd-top { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; margin-bottom: .75rem; }
    .rkd-sum { font-size: .875rem; color: var(--gray-500); }
    .rkd-sum b { color: var(--gray-950); font-size: 1rem; }
    .dark .rkd-sum b { color: var(--gray-50); }
    .rkd-search { border: 1px solid var(--gray-300); border-radius: .5rem; padding: .4rem .625rem; min-width: 16rem; background: #fff; color: var(--gray-950); }
    .dark .rkd-search { border-color: var(--gray-600); background: var(--gray-900); color: var(--gray-100); }
    .rkd-wrap { max-height: 60vh; overflow: auto; border: 1px solid var(--gray-200); border-radius: .75rem; }
    .dark .rkd-wrap { border-color: var(--gray-700); }
    .rkd-table { width: 100%; border-collapse: collapse; font-size: .8125rem; font-variant-numeric: tabular-nums; }
    .rkd-table th { position: sticky; top: 0; z-index: 1; background: var(--gray-50); text-align: left; font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .5rem .75rem; white-space: nowrap; }
    .dark .rkd-table th { background: var(--gray-800); }
    .rkd-table td { padding: .5rem .75rem; border-top: 1px solid var(--gray-100); vertical-align: top; }
    .dark .rkd-table td { border-top-color: var(--gray-800); }
    .rkd-r { text-align: right !important; white-space: nowrap; }
    .rkd-muted { color: var(--gray-500); }
    .rkd-chip { padding: .0625rem .5rem; border-radius: 9999px; font-size: .6875rem; font-weight: 600; white-space: nowrap; }
    .rkd-ok { background: var(--success-100); color: var(--success-700); }
    .dark .rkd-ok { background: var(--success-900); color: var(--success-300); }
    .rkd-warn { background: var(--warning-100); color: var(--warning-700); }
    .dark .rkd-warn { background: var(--warning-900); color: var(--warning-300); }
    .rkd-bad { background: var(--danger-100); color: var(--danger-700); }
    .dark .rkd-bad { background: var(--danger-900); color: var(--danger-300); }
</style>

<div x-data="{ q: '' }">
    <div class="rkd-top">
        <div class="rkd-sum"><b>{{ count($rows) }}</b> transaksi · total <b>{{ $rp($total) }}</b></div>
        <input type="search" class="rkd-search" x-model="q" placeholder="Cari nama, NISN, kwitansi, kelas…">
    </div>

    @if (empty($rows))
        <div class="rkd-muted" style="padding:1rem;">Tidak ada data.</div>
    @else
        <div class="rkd-wrap">
            <table class="rkd-table">
                <thead>
                    <tr><th>Waktu</th><th>Kwitansi</th><th>Siswa</th><th>Kelas</th><th>Metode</th><th class="rkd-r">Jumlah</th><th>Status</th><th>Dibayarkan untuk</th><th>Dicatat oleh</th></tr>
                </thead>
                <tbody>
                    @foreach ($shown as $r)
                        <tr x-show="$el.innerText.toLowerCase().includes(q.toLowerCase())">
                            <td style="white-space:nowrap;">{{ $r['paid_at'] }}<br><span class="rkd-muted">{{ $r['time'] }}</span></td>
                            <td>{{ $r['receipt_no'] }}</td>
                            <td><b>{{ $r['student'] }}</b><br><span class="rkd-muted">NISN {{ $r['nisn'] }}</span></td>
                            <td>{{ $r['class'] }}</td>
                            <td>{{ $r['method'] }}</td>
                            <td class="rkd-r" style="font-weight:600;">{{ $rp($r['amount']) }}</td>
                            <td><span class="rkd-chip {{ $stateClass($r['status']) }}">{{ $r['status'] }}</span></td>
                            <td class="rkd-muted">{{ $r['for'] ?: '-' }}</td>
                            <td>{{ $r['creator'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($rows) > $limit)
            <div class="rkd-muted" style="margin-top:.5rem; font-size:.75rem;">Menampilkan {{ $limit }} transaksi terbaru dari {{ count($rows) }}. Persempit periode atau gunakan unduhan "Rincian transaksi" untuk daftar lengkap.</div>
        @endif
    @endif
</div>
