@php
    $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $todayMap = collect($today)->pluck('total', 'name');
    $max = max(1, (int) collect($month)->max('total'));
    $sum = max(1, (int) collect($month)->sum('total'));
@endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Pengeluaran per kategori" description="Bulan ini, dan yang keluar hari ini">
        <style>
            .xc-row { padding: .45rem 0; border-top: 1px solid var(--gray-100); font-size: .8125rem; }
            .dark .xc-row { border-top-color: var(--gray-800); }
            .xc-top { display: flex; justify-content: space-between; gap: .75rem; font-variant-numeric: tabular-nums; }
            .xc-bar { height: .375rem; border-radius: 9999px; background: var(--gray-200); overflow: hidden; margin-top: .25rem; }
            .dark .xc-bar { background: var(--gray-700); }
            .xc-bar > div { height: 100%; background: var(--warning-500); }
            .xc-muted { color: var(--gray-500); font-size: .6875rem; }
        </style>
        @forelse ($month as $c)
            <div class="xc-row">
                <div class="xc-top"><b>{{ $c['name'] }}</b><b>{{ $rp($c['total']) }}</b></div>
                <div class="xc-bar"><div style="width: {{ round($c['total'] / $max * 100) }}%"></div></div>
                <div class="xc-muted">{{ round($c['total'] / $sum * 100) }}% dari bulan ini · {{ $c['count'] }} catatan · hari ini {{ $rp((int) ($todayMap[$c['name']] ?? 0)) }}</div>
            </div>
        @empty
            <div class="xc-muted" style="font-size:.875rem;">Belum ada pengeluaran bulan ini.</div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
