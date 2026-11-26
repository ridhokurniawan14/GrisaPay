@php
    $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $diff = $counted === null ? null : $counted - $expected;
@endphp
<div>
    <style>
        .kc { border: 1px solid var(--gray-200); border-radius: .75rem; padding: .75rem .875rem; font-size: .875rem; font-variant-numeric: tabular-nums; }
        .dark .kc { border-color: var(--gray-700); }
        .kc-r { display: flex; justify-content: space-between; gap: 1rem; padding: .125rem 0; color: var(--gray-600); }
        .dark .kc-r { color: var(--gray-400); }
        .kc-t { display: flex; justify-content: space-between; gap: 1rem; padding-top: .5rem; margin-top: .375rem; border-top: 1px solid var(--gray-200); font-weight: 700; font-size: 1.0625rem; }
        .dark .kc-t { border-top-color: var(--gray-700); }
        .kc-d { margin-top: .625rem; padding: .5rem .75rem; border-radius: .5rem; font-weight: 600; display: flex; justify-content: space-between; gap: 1rem; }
        .kc-ok { background: var(--success-50); color: var(--success-700); }
        .dark .kc-ok { background: var(--success-950); color: var(--success-300); }
        .kc-bad { background: var(--danger-50); color: var(--danger-700); }
        .dark .kc-bad { background: var(--danger-950); color: var(--danger-300); }
        .kc-more { background: var(--warning-50); color: var(--warning-700); }
        .dark .kc-more { background: var(--warning-950); color: var(--warning-300); }
    </style>
    <div class="kc">
        <div class="kc-r"><span>Modal awal</span><span>{{ $rp($opening) }}</span></div>
        <div class="kc-r"><span>Tunai masuk ({{ $count }} transaksi)</span><span>+ {{ $rp($cash) }}</span></div>
        <div class="kc-t"><span>Harus disetor</span><span>{{ $rp($expected) }}</span></div>
    </div>
    @if ($diff !== null)
        <div class="kc-d {{ $diff === 0 ? 'kc-ok' : ($diff < 0 ? 'kc-bad' : 'kc-more') }}">
            <span>{{ $diff === 0 ? 'Kas cocok' : ($diff < 0 ? 'Selisih kurang' : 'Selisih lebih') }}</span>
            <span>{{ $diff === 0 ? '✓' : $rp(abs($diff)) }}</span>
        </div>
    @endif
</div>
