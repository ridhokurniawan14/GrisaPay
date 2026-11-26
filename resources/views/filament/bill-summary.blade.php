@php
    $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $t = $summary['total'];
    $payable = max(0, $t['bill'] - $t['discount']);
    $percent = $payable > 0 ? (int) floor($t['paid'] / $payable * 100) : 0;
    $initial = mb_strtoupper(mb_substr($student->name, 0, 1));
@endphp

<style>
    .bs { display: grid; gap: 1rem; font-size: .875rem; color: var(--gray-950); }
    .dark .bs { color: var(--gray-50); }
    .bs-muted { color: var(--gray-500); }
    .bs-head { display: flex; align-items: center; gap: .875rem; }
    .bs-avatar { width: 2.75rem; height: 2.75rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.125rem; background: var(--primary-100); color: var(--primary-700); flex: none; }
    .dark .bs-avatar { background: var(--primary-900); color: var(--primary-200); }
    .bs-name { font-weight: 600; font-size: 1rem; line-height: 1.25rem; }
    .bs-chips { display: flex; flex-wrap: wrap; gap: .375rem; margin-top: .25rem; }
    .bs-chip { padding: .125rem .5rem; border-radius: 9999px; font-size: .75rem; background: var(--gray-100); color: var(--gray-700); }
    .dark .bs-chip { background: var(--gray-800); color: var(--gray-300); }
    .bs-chip-primary { background: var(--primary-100); color: var(--primary-700); }
    .dark .bs-chip-primary { background: var(--primary-900); color: var(--primary-200); }
    .bs-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(8.5rem, 1fr)); gap: .625rem; }
    .bs-stat { border: 1px solid var(--gray-200); border-radius: .75rem; padding: .625rem .75rem; }
    .dark .bs-stat { border-color: var(--gray-700); }
    .bs-stat-label { font-size: .6875rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); }
    .bs-stat-value { font-weight: 700; font-size: 1rem; margin-top: .125rem; font-variant-numeric: tabular-nums; }
    .bs-danger { color: var(--danger-600); }
    .dark .bs-danger { color: var(--danger-400); }
    .bs-success { color: var(--success-600); }
    .dark .bs-success { color: var(--success-400); }
    .bs-bar { height: .5rem; border-radius: 9999px; background: var(--gray-200); overflow: hidden; }
    .dark .bs-bar { background: var(--gray-700); }
    .bs-bar > span { display: block; height: 100%; background: var(--success-500); border-radius: 9999px; }
    .bs-card { border: 1px solid var(--gray-200); border-radius: .75rem; overflow: hidden; }
    .dark .bs-card { border-color: var(--gray-700); }
    .bs-card-warn { border-color: var(--danger-300); }
    .dark .bs-card-warn { border-color: var(--danger-700); }
    .bs-card-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .625rem .875rem; background: var(--gray-50); }
    .dark .bs-card-head { background: var(--gray-800); }
    .bs-card-warn .bs-card-head { background: var(--danger-50); }
    .dark .bs-card-warn .bs-card-head { background: var(--danger-950); }
    .bs-card-title { font-weight: 600; }
    .bs-badge { padding: .0625rem .5rem; border-radius: 9999px; font-size: .6875rem; font-weight: 600; }
    .bs-badge-ok { background: var(--success-100); color: var(--success-700); }
    .dark .bs-badge-ok { background: var(--success-900); color: var(--success-300); }
    .bs-badge-due { background: var(--warning-100); color: var(--warning-700); }
    .dark .bs-badge-due { background: var(--warning-900); color: var(--warning-300); }
    .bs-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
    .bs-table th { font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); text-align: right; padding: .375rem .875rem; }
    .bs-table th:first-child, .bs-table td:first-child { text-align: left; }
    .bs-table td { text-align: right; padding: .375rem .875rem; border-top: 1px solid var(--gray-100); }
    .dark .bs-table td { border-top-color: var(--gray-800); }
    .bs-table td:first-child { padding-left: 1.25rem; }
    .bs-table .bs-nol { color: var(--gray-400); }
    .bs-scroll { overflow-x: auto; }
    .bs-hist td { vertical-align: top; }
    .bs-chip-ok { background: var(--success-100); color: var(--success-700); }
    .dark .bs-chip-ok { background: var(--success-900); color: var(--success-300); }
    .bs-chip-warn { background: var(--warning-100); color: var(--warning-700); }
    .dark .bs-chip-warn { background: var(--warning-900); color: var(--warning-300); }
    .bs-chip-bad { background: var(--danger-100); color: var(--danger-700); }
    .dark .bs-chip-bad { background: var(--danger-900); color: var(--danger-300); }
    .bs-empty { text-align: center; padding: 2rem 1rem; border: 1px dashed var(--gray-300); border-radius: .75rem; color: var(--gray-500); }
    .dark .bs-empty { border-color: var(--gray-600); }
</style>

<div class="bs">
    <div class="bs-head">
        <div class="bs-avatar">{{ $initial }}</div>
        <div>
            <div class="bs-name">{{ $student->name }}</div>
            <div class="bs-chips">
                <span class="bs-chip">NISN {{ $student->nisn ?? '-' }}</span>
                <span class="bs-chip bs-chip-primary">{{ $bill?->classroom_name ?? 'Belum ada kelas' }}</span>
                <span class="bs-chip">TA {{ $year->code }}</span>
            </div>
        </div>
    </div>

    @if ($summary['rows'] === [])
        <div class="bs-empty">Belum ada tagihan untuk siswa ini.</div>
    @else
        <div class="bs-stats">
            <div class="bs-stat">
                <div class="bs-stat-label">Total tagihan</div>
                <div class="bs-stat-value">{{ $rp($t['bill']) }}</div>
            </div>
            <div class="bs-stat">
                <div class="bs-stat-label">Diskon</div>
                <div class="bs-stat-value">{{ $rp($t['discount']) }}</div>
            </div>
            <div class="bs-stat">
                <div class="bs-stat-label">Dibayar</div>
                <div class="bs-stat-value bs-success">{{ $rp($t['paid']) }}</div>
            </div>
            <div class="bs-stat">
                <div class="bs-stat-label">Sisa</div>
                <div class="bs-stat-value {{ $t['remaining'] > 0 ? 'bs-danger' : 'bs-success' }}">{{ $rp($t['remaining']) }}</div>
            </div>
        </div>

        <div>
            <div class="bs-bar"><span style="width: {{ $percent }}%"></span></div>
            <div class="bs-muted" style="margin-top:.25rem; font-size:.75rem;">{{ $percent }}% terbayar</div>
        </div>

        @foreach ($summary['rows'] as $row)
            @php $carry = $row['label'] === 'Tunggakan Sebelumnya'; @endphp
            <div class="bs-card {{ $carry ? 'bs-card-warn' : '' }}">
                <div class="bs-card-head">
                    <span class="bs-card-title {{ $carry ? 'bs-danger' : '' }}">{{ $row['label'] }}</span>
                    <span style="display:flex; align-items:center; gap:.5rem;">
                        <span class="bs-badge {{ $row['remaining'] === 0 ? 'bs-badge-ok' : 'bs-badge-due' }}">
                            {{ $row['remaining'] === 0 ? 'Lunas' : 'Sisa '.$rp($row['remaining']) }}
                        </span>
                    </span>
                </div>
                <div class="bs-scroll">
                    <table class="bs-table">
                        <thead>
                            <tr><th>Rincian</th><th>Tagihan</th><th>Diskon</th><th>Dibayar</th><th>Sisa</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($row['items'] as $line)
                                <tr>
                                    <td>{{ $line['name'] }}</td>
                                    <td>{{ $rp($line['amount']) }}</td>
                                    <td class="{{ $line['discount'] === 0 ? 'bs-nol' : '' }}">{{ $rp($line['discount']) }}</td>
                                    <td class="{{ $line['paid'] === 0 ? 'bs-nol' : '' }}">{{ $rp($line['paid']) }}</td>
                                    <td style="font-weight:600;">{{ $rp($line['remaining']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif

    @php $payments = $payments ?? collect(); $paymentsTotal = $paymentsTotal ?? $payments->count(); @endphp
    <div class="bs-card">
        <div class="bs-card-head">
            <span class="bs-card-title">Riwayat pembayaran</span>
            <span class="bs-chip">{{ $paymentsTotal > $payments->count() ? $payments->count().' terakhir dari '.$paymentsTotal : $paymentsTotal }} transaksi</span>
        </div>
        @if ($payments->isEmpty())
            <div class="bs-muted" style="padding:.75rem .875rem;">Belum ada pembayaran.</div>
        @else
            <div class="bs-scroll">
                <table class="bs-table bs-hist">
                    <thead><tr><th>Waktu</th><th>Kwitansi</th><th>Metode</th><th>Jumlah</th><th>Status</th><th>Dicatat</th><th>Cetak</th></tr></thead>
                    <tbody>
                        @foreach ($payments as $p)
                            @php
                                $cls = match ($p->status) { 'sah' => 'bs-chip-ok', 'menunggu' => 'bs-chip-warn', 'ditolak' => 'bs-chip-bad', default => '' };
                                $txt = match ($p->status) { 'sah' => 'Sah', 'menunggu' => 'Menunggu verifikasi', 'ditolak' => 'Ditolak', default => 'Void' };
                            @endphp
                            <tr>
                                <td style="white-space:nowrap;">{{ $p->paid_at->format('d/m/Y') }}<br><span class="bs-muted">{{ $p->paid_at->format('H:i') }} WIB</span></td>
                                <td>{{ $p->receipt_no ?? '-' }}</td>
                                <td>{{ $p->methodText() }}</td>
                                <td style="font-weight:600;">{{ $rp($p->amount) }}</td>
                                <td><span class="bs-chip {{ $cls }}">{{ $txt }}</span></td>
                                <td>{{ $p->creator?->name ?? '-' }}</td>
                                <td>{{ $p->prints->count() }}×</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if (($historyUrl ?? null) && $paymentsTotal > 0)
            <div style="padding:.625rem .875rem; border-top:1px solid var(--gray-200);">
                <a href="{{ $historyUrl }}" class="bs-link" style="font-weight:600; font-size:.8125rem; color: var(--primary-600); text-decoration: none;">
                    Buka riwayat lengkap{{ $paymentsTotal > $payments->count() ? ' ('.$paymentsTotal.' transaksi)' : '' }} &rarr;
                </a>
                <span class="bs-muted" style="font-size:.75rem;"> · bisa dicari berdasarkan nomor kwitansi</span>
            </div>
        @endif
    </div>
</div>
