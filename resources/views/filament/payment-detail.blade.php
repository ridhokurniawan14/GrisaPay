@php
    $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $cls = match ($payment->status) { 'sah' => 'pd-ok', 'menunggu' => 'pd-warn', 'ditolak' => 'pd-bad', default => 'pd-gray' };
    $txt = match ($payment->status) { 'sah' => 'Sah', 'menunggu' => 'Menunggu verifikasi', 'ditolak' => 'Ditolak', default => 'Void' };
    $when = fn ($t): string => $t ? $t->format('d/m/Y H:i').' WIB' : '-';
@endphp

<style>
    .pd { display: grid; gap: 1rem; font-size: .875rem; color: var(--gray-950); }
    .dark .pd { color: var(--gray-50); }
    .pd-muted { color: var(--gray-500); }
    .pd-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
    .pd-no { font-size: 1.125rem; font-weight: 700; }
    .pd-amount { font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .pd-chip { padding: .125rem .625rem; border-radius: 9999px; font-size: .75rem; font-weight: 600; background: var(--gray-100); color: var(--gray-700); }
    .dark .pd-chip { background: var(--gray-800); color: var(--gray-300); }
    .pd-ok { background: var(--success-100) !important; color: var(--success-700) !important; }
    .dark .pd-ok { background: var(--success-900) !important; color: var(--success-300) !important; }
    .pd-warn { background: var(--warning-100) !important; color: var(--warning-700) !important; }
    .dark .pd-warn { background: var(--warning-900) !important; color: var(--warning-300) !important; }
    .pd-bad { background: var(--danger-100) !important; color: var(--danger-700) !important; }
    .dark .pd-bad { background: var(--danger-900) !important; color: var(--danger-300) !important; }
    .pd-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: .625rem; }
    .pd-cell { border: 1px solid var(--gray-200); border-radius: .625rem; padding: .5rem .75rem; }
    .dark .pd-cell { border-color: var(--gray-700); }
    .pd-cell small { display: block; font-size: .6875rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); }
    .pd-card { border: 1px solid var(--gray-200); border-radius: .75rem; overflow: hidden; }
    .dark .pd-card { border-color: var(--gray-700); }
    .pd-head { padding: .625rem .875rem; font-weight: 600; background: var(--gray-50); }
    .dark .pd-head { background: var(--gray-800); }
    .pd-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
    .pd-table th { text-align: left; font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .375rem .875rem; }
    .pd-table td { padding: .375rem .875rem; border-top: 1px solid var(--gray-100); }
    .dark .pd-table td { border-top-color: var(--gray-800); }
    .pd-r { text-align: right !important; }
</style>

<div class="pd">
    <div class="pd-top">
        <div>
            <div class="pd-no">{{ $payment->receipt_no ?? '(belum ada nomor kwitansi)' }}</div>
            <div class="pd-muted">{{ $payment->student->name }} · NISN {{ $payment->student->nisn ?? '-' }}</div>
        </div>
        <div style="text-align:right;">
            <div class="pd-amount">{{ $rp($payment->amount) }}</div>
            <span class="pd-chip {{ $cls }}">{{ $txt }}</span>
        </div>
    </div>

    <div class="pd-grid">
        <div class="pd-cell"><small>Waktu bayar</small>{{ $when($payment->paid_at) }}</div>
        <div class="pd-cell"><small>Metode</small>{{ $payment->methodText() }}@if ($payment->reference) · {{ $payment->reference }}@endif</div>
        <div class="pd-cell"><small>Penyetor</small>{{ $payment->payer_name ?? '-' }}</div>
        <div class="pd-cell"><small>Dicatat oleh</small>{{ $payment->creator?->name ?? '-' }}</div>
        <div class="pd-cell"><small>{{ $payment->status === 'ditolak' ? 'Ditolak oleh' : 'Diverifikasi oleh' }}</small>{{ $payment->verifier?->name ?? '-' }}<br><span class="pd-muted">{{ $when($payment->verified_at) }}</span></div>
        <div class="pd-cell"><small>Catatan</small>{{ $payment->note ?? '-' }}</div>
    </div>

    @if ($payment->status === 'ditolak' && $payment->reject_reason)
        <div class="pd-cell" style="border-color: var(--danger-300);"><small>Alasan penolakan</small>{{ $payment->reject_reason }}</div>
    @endif

    <div class="pd-card">
        <div class="pd-head">Dibagi ke tagihan</div>
        @if ($payment->allocations->isEmpty())
            <div class="pd-muted" style="padding:.75rem .875rem;">Belum dibagi ke tagihan (menunggu verifikasi).</div>
        @else
            <table class="pd-table">
                <thead><tr><th>Rincian</th><th>Kategori</th><th>Tahun ajaran</th><th class="pd-r">Jumlah</th></tr></thead>
                <tbody>
                    @foreach ($payment->allocations as $a)
                        <tr>
                            <td>{{ $a->billItem->name }}</td>
                            <td>{{ $a->billItem->category->receipt_label }}</td>
                            <td>{{ $a->billItem->bill->academicYear->code }}</td>
                            <td class="pd-r">{{ $rp($a->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="pd-card">
        <div class="pd-head">Riwayat cetak struk <span class="pd-muted" style="font-weight:400;">· {{ $payment->prints->count() }}×</span></div>
        @if ($payment->prints->isEmpty())
            <div class="pd-muted" style="padding:.75rem .875rem;">Belum pernah dicetak.</div>
        @else
            <table class="pd-table">
                <thead><tr><th>Cetakan</th><th>Oleh</th><th>Waktu</th></tr></thead>
                <tbody>
                    @foreach ($payment->prints as $print)
                        <tr><td>{{ $print->label() }}</td><td>{{ $print->printer?->name ?? '-' }}</td><td>{{ $when($print->printed_at) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($payment->voids->isNotEmpty())
        <div class="pd-card">
            <div class="pd-head">Pengajuan void</div>
            <table class="pd-table">
                <thead><tr><th>Diajukan</th><th>Alasan</th><th>Status</th><th>Diputuskan</th></tr></thead>
                <tbody>
                    @foreach ($payment->voids as $v)
                        <tr>
                            <td>{{ $v->requester?->name ?? '-' }}<br><span class="pd-muted">{{ $when($v->created_at) }}</span></td>
                            <td>{{ $v->reason }}</td>
                            <td>{{ ucfirst($v->status) }}</td>
                            <td>{{ $v->decider?->name ?? '-' }}<br><span class="pd-muted">{{ $when($v->decided_at) }}</span>@if ($v->decision_note)<br>{{ $v->decision_note }}@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
