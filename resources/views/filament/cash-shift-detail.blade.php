@php
    $rp = fn (?int $n): string => $n === null ? '-' : 'Rp '.number_format($n, 0, ',', '.');
    $when = fn ($t): string => $t ? $t->format('d/m/Y H:i').' WIB' : '-';
    $sah = $payments->where('status', \App\Models\Payment::PAID);
    $cash = (int) $sah->where('method', \App\Models\Payment::CASH)->sum('amount');
    $transfer = (int) $sah->where('method', \App\Models\Payment::TRANSFER)->sum('amount');
    $qris = (int) $sah->where('method', \App\Models\Payment::QRIS)->sum('amount');
    $diff = $shift->difference;
    $labels = \App\Services\CashRecap::GRADE_LABELS;
    $gradeStats = [];
    foreach ($labels as $g => $label) {
        $set = $sah->filter(fn ($p): bool => $place[$p->id]['grade'] === $g);
        $gradeStats[$g] = [
            'label' => $label,
            'total' => (int) $set->sum('amount'),
            'cash' => (int) $set->where('method', \App\Models\Payment::CASH)->sum('amount'),
            'students' => $set->pluck('student_id')->unique()->count(),
            'count' => $set->count(),
        ];
    }
    $classStats = $sah->groupBy(fn ($p): string => $place[$p->id]['class'])
        ->map(fn ($set, $name): array => ['name' => (string) $name, 'grade' => $place[$set->first()->id]['grade'], 'students' => $set->pluck('student_id')->unique()->count(), 'total' => (int) $set->sum('amount')])
        ->sortBy(fn ($c): string => ($c['grade'] === 0 ? '9' : '1').$c['name'])->values();
@endphp

<style>
    .sd { display: grid; gap: 1rem; font-size: .875rem; }
    .sd-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: .625rem; }
    .sd-cell { border: 1px solid var(--gray-200); border-radius: .625rem; padding: .5rem .75rem; }
    .dark .sd-cell { border-color: var(--gray-700); }
    .sd-cell small { display: block; font-size: .6875rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); }
    .sd-cell b { font-variant-numeric: tabular-nums; }
    .sd-good b { color: var(--success-600); } .dark .sd-good b { color: var(--success-400); }
    .sd-bad b { color: var(--danger-600); } .dark .sd-bad b { color: var(--danger-400); }
    .sd-warn b { color: var(--warning-600); } .dark .sd-warn b { color: var(--warning-400); }
    .sd-wrap { max-height: 45vh; overflow: auto; border: 1px solid var(--gray-200); border-radius: .75rem; }
    .dark .sd-wrap { border-color: var(--gray-700); }
    .sd-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
    .sd-table th { position: sticky; top: 0; background: var(--gray-50); text-align: left; font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .4rem .75rem; }
    .dark .sd-table th { background: var(--gray-800); }
    .sd-table td { padding: .4rem .75rem; border-top: 1px solid var(--gray-100); }
    .dark .sd-table td { border-top-color: var(--gray-800); }
    .sd-r { text-align: right !important; white-space: nowrap; }
    .sd-muted { color: var(--gray-500); }
    .sd-sec { font-weight: 600; font-size: .8125rem; }
    .sd-tile { text-align: left; font: inherit; color: inherit; cursor: pointer; border: 1px solid var(--gray-200); border-radius: .75rem; padding: .625rem .875rem; background: transparent; }
    .dark .sd-tile { border-color: var(--gray-700); }
    .sd-tile:hover { border-color: var(--gray-400); }
    .sd-tile.on { border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500); }
    .sd-tile small { display: block; font-size: .6875rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); }
    .sd-tile b { display: block; font-size: 1.125rem; font-variant-numeric: tabular-nums; }
    .sd-tile span { font-size: .75rem; color: var(--gray-500); }
    .sd-chip { font: inherit; font-size: .75rem; cursor: pointer; border: 1px solid var(--gray-300); border-radius: 9999px; padding: .125rem .625rem; background: transparent; color: inherit; }
    .dark .sd-chip { border-color: var(--gray-600); }
    .sd-chip.on { background: var(--primary-600); border-color: var(--primary-600); color: #fff; }
    .sd-void { opacity: .5; text-decoration: line-through; }
</style>

<div class="sd" x-data="{ f: null }">
    <div class="sd-grid">
        <div class="sd-cell"><small>Modal awal</small><b>{{ $rp($shift->opening_cash) }}</b></div>
        <div class="sd-cell"><small>Tunai sah selama shift</small><b>{{ $rp($cash) }}</b></div>
        <div class="sd-cell"><small>Tunai seharusnya di laci</small><b>{{ $rp($shift->expectedCash()) }}</b></div>
        <div class="sd-cell"><small>Tunai dihitung kasir</small><b>{{ $rp($shift->counted_cash) }}</b></div>
        <div class="sd-cell {{ $diff === null ? '' : ($diff === 0 ? 'sd-good' : ($diff < 0 ? 'sd-bad' : 'sd-warn')) }}"><small>Selisih</small><b>{{ $diff === null ? '-' : ($diff === 0 ? 'Cocok' : ($diff < 0 ? 'Kurang ' : 'Lebih ').$rp(abs($diff))) }}</b></div>
        <div class="sd-cell"><small>Transfer / QRIS (sah)</small><b>{{ $rp($transfer) }} / {{ $rp($qris) }}</b></div>
    </div>

    <div class="sd-grid">
        <div class="sd-cell"><small>Dibuka</small>{{ $when($shift->opened_at) }}</div>
        <div class="sd-cell"><small>Ditutup</small>{{ $when($shift->closed_at) }}</div>
        <div class="sd-cell"><small>Ditinjau oleh</small>{{ $shift->reviewer?->name ?? '-' }}<br><span class="sd-muted">{{ $when($shift->reviewed_at) }}</span></div>
    </div>

    @if ($shift->close_note)
        <div class="sd-cell"><small>Catatan kasir</small>{{ $shift->close_note }}</div>
    @endif
    @if ($shift->review_note)
        <div class="sd-cell"><small>Catatan bendahara</small>{{ $shift->review_note }}</div>
    @endif

    <div>
        <div class="sd-sec" style="margin-bottom:.375rem;">Uang masuk per tingkat <span class="sd-muted" style="font-weight:400;">· klik untuk melihat siapa saja yang bayar</span></div>
        <div class="sd-grid">
            @foreach ($gradeStats as $g => $st)
                @if ($g !== 0 || $st['total'] > 0)
                    <button type="button" class="sd-tile" :class="{ 'on': f === 'g:{{ $g }}' }" @click="f = (f === 'g:{{ $g }}') ? null : 'g:{{ $g }}'">
                        <small>{{ $st['label'] }}</small>
                        <b>{{ $rp($st['total']) }}</b>
                        <span>{{ $st['students'] }} siswa · {{ $st['count'] }} transaksi · tunai {{ $rp($st['cash']) }}</span>
                    </button>
                @endif
            @endforeach
        </div>
        @if ($classStats->isNotEmpty())
            <div style="display:flex; flex-wrap:wrap; gap:.375rem; margin-top:.5rem;">
                @foreach ($classStats as $c)
                    <button type="button" class="sd-chip" :class="{ 'on': f === @js('c:'.$c['name']) }" @click="f = (f === @js('c:'.$c['name'])) ? null : @js('c:'.$c['name'])">
                        {{ $c['name'] }} · {{ $c['students'] }} siswa · {{ $rp($c['total']) }}
                    </button>
                @endforeach
            </div>
        @endif
        <div class="sd-muted" style="font-size:.75rem; margin-top:.375rem;" x-show="f !== null" x-cloak>
            Daftar di bawah difilter. <a href="#" style="text-decoration:underline;" @click.prevent="f = null">Tampilkan semua</a>
        </div>
    </div>

    <div class="sd-wrap">
        <table class="sd-table">
            <thead><tr><th>Waktu</th><th>Kwitansi</th><th>Siswa</th><th>Kelas</th><th>Metode</th><th class="sd-r">Jumlah</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($payments as $p)
                    <tr class="{{ $p->status === \App\Models\Payment::VOID ? 'sd-void' : '' }}"
                        x-show="f === null || f === 'g:{{ $place[$p->id]['grade'] }}' || f === @js('c:'.$place[$p->id]['class'])">
                        <td style="white-space:nowrap;">{{ $p->paid_at->format('H:i') }} <span class="sd-muted">WIB</span></td>
                        <td>{{ $p->receipt_no ?? '-' }}</td>
                        <td>{{ $p->student->name }}</td>
                        <td class="sd-muted">{{ $place[$p->id]['class'] }}</td>
                        <td>{{ $p->methodText() }}</td>
                        <td class="sd-r" style="font-weight:600;">{{ $rp($p->amount) }}</td>
                        <td>{{ match ($p->status) { \App\Models\Payment::PAID => 'Sah', \App\Models\Payment::PENDING => 'Menunggu verifikasi', \App\Models\Payment::REJECTED => 'Ditolak', default => 'Void' } }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="sd-muted" style="padding:.75rem;">Belum ada pembayaran pada shift ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
