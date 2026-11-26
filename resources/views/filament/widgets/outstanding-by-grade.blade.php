@php $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.'); @endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Sisa tagihan per tingkat" description="Tagihan setelah diskon dikurangi pembayaran sah">
        <style>
            .dw { width: 100%; border-collapse: collapse; font-size: .8125rem; font-variant-numeric: tabular-nums; }
            .dw th { text-align: right; font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .25rem .5rem; white-space: nowrap; }
            .dw td { text-align: right; padding: .45rem .5rem; border-top: 1px solid var(--gray-100); vertical-align: top; }
            .dark .dw td { border-top-color: var(--gray-800); }
            .dw th:first-child, .dw td:first-child { text-align: left; }
            .dw-bar { height: .375rem; border-radius: 9999px; background: var(--gray-200); overflow: hidden; margin-top: .25rem; }
            .dark .dw-bar { background: var(--gray-700); }
            .dw-bar > div { height: 100%; background: var(--success-500); }
            .dw-bad { color: var(--danger-600); font-weight: 600; } .dark .dw-bad { color: var(--danger-400); }
            .dw-muted { color: var(--gray-500); font-size: .6875rem; }
        </style>
        @if (empty($rows))
            <div class="dw-muted" style="font-size:.875rem;">Belum ada tagihan.</div>
        @else
            <table class="dw">
                <thead><tr><th>Tingkat</th><th>Tagihan</th><th>Dibayar</th><th>Sisa</th></tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        @php $pct = $r['due'] > 0 ? min(100, (int) round($r['paid'] / $r['due'] * 100)) : 0; @endphp
                        <tr>
                            <td>{{ $r['label'] }}<div class="dw-bar"><div style="width: {{ $pct }}%"></div></div></td>
                            <td>{{ $rp($r['due']) }}</td>
                            <td>{{ $rp($r['paid']) }}<div class="dw-muted">{{ $pct }}%</div></td>
                            <td class="dw-bad">{{ $rp($r['remaining']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
