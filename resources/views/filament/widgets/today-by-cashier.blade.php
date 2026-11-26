@php $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.'); $monthMap = collect($month)->keyBy('name'); @endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Penerimaan per kasir hari ini" description="Tunai dan non-tunai, beserta total bulan ini">
        <style>
            .tc { width: 100%; border-collapse: collapse; font-size: .8125rem; font-variant-numeric: tabular-nums; }
            .tc th { text-align: right; font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .25rem .5rem; white-space: nowrap; }
            .tc td { text-align: right; padding: .45rem .5rem; border-top: 1px solid var(--gray-100); white-space: nowrap; }
            .dark .tc td { border-top-color: var(--gray-800); }
            .tc th:first-child, .tc td:first-child { text-align: left; white-space: normal; }
            .tc-muted { color: var(--gray-500); font-size: .6875rem; }
        </style>
        @if (empty($rows))
            <div class="tc-muted" style="font-size:.875rem;">Belum ada penerimaan hari ini.</div>
        @else
            <div style="overflow:auto;">
            <table class="tc">
                <thead><tr><th>Kasir</th><th>Tunai</th><th>Non-tunai</th><th>Total</th><th>Bulan ini</th></tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td><b>{{ $r['name'] }}</b><div class="tc-muted">{{ $r['count'] }} trx · terakhir {{ $r['last'] }}</div></td>
                            <td>{{ $rp($r['cash']) }}</td>
                            <td>{{ $rp($r['other']) }}</td>
                            <td><b>{{ $rp($r['total']) }}</b></td>
                            <td class="tc-muted">{{ $rp((int) ($monthMap[$r['name']]['total'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
