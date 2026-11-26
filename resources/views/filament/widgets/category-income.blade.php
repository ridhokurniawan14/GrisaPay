@php
    $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $todayMap = collect($today)->pluck('total', 'name');
    $max = max(1, (int) collect($month)->max('total'));
    $sum = max(1, (int) collect($month)->sum('total'));
@endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Penerimaan per kategori" description="Bulan ini, dan yang masuk hari ini">
        <style>
            .ci-row { padding: .45rem 0; border-top: 1px solid var(--gray-100); font-size: .8125rem; }
            .dark .ci-row { border-top-color: var(--gray-800); }
            .ci-top { display: flex; justify-content: space-between; gap: .75rem; font-variant-numeric: tabular-nums; }
            .ci-bar { height: .375rem; border-radius: 9999px; background: var(--gray-200); overflow: hidden; margin-top: .25rem; }
            .dark .ci-bar { background: var(--gray-700); }
            .ci-bar > div { height: 100%; background: var(--primary-500); }
            .ci-muted { color: var(--gray-500); font-size: .6875rem; }
        </style>
        @forelse ($month as $c)
            <div class="ci-row">
                <div class="ci-top"><b>{{ $c['name'] }}</b><span><b>{{ $rp($c['total']) }}</b></span></div>
                <div class="ci-bar"><div style="width: {{ round($c['total'] / $max * 100) }}%"></div></div>
                <div class="ci-muted">{{ round($c['total'] / $sum * 100) }}% dari bulan ini · {{ $c['count'] }} alokasi · hari ini {{ $rp((int) ($todayMap[$c['name']] ?? 0)) }}</div>
            </div>
        @empty
            <div class="ci-muted" style="font-size:.875rem;">Belum ada penerimaan bulan ini.</div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
