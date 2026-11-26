@php $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.'); @endphp
<x-filament-widgets::widget>
    <x-filament::section heading="10 siswa dengan sisa tagihan terbesar" description="Semua tahun ajaran, termasuk tunggakan awal dan alumni">
        <style>
            .ta { width: 100%; border-collapse: collapse; font-size: .8125rem; font-variant-numeric: tabular-nums; }
            .ta th { text-align: left; font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .25rem .625rem; white-space: nowrap; }
            .ta td { padding: .45rem .625rem; border-top: 1px solid var(--gray-100); }
            .dark .ta td { border-top-color: var(--gray-800); }
            .ta .r { text-align: right; white-space: nowrap; }
            .ta-muted { color: var(--gray-500); }
            .ta-bad { color: var(--danger-600); font-weight: 700; } .dark .ta-bad { color: var(--danger-400); }
        </style>
        @if (empty($rows))
            <div class="ta-muted" style="font-size:.875rem;">Tidak ada tunggakan. 🎉</div>
        @else
            <div style="overflow:auto;">
            <table class="ta">
                <thead><tr><th>#</th><th>Siswa</th><th>Kelas terakhir</th><th>Status</th><th class="r">Tagihan</th><th class="r">Dibayar</th><th class="r">Sisa</th></tr></thead>
                <tbody>
                    @foreach ($rows as $i => $r)
                        <tr>
                            <td class="ta-muted">{{ $i + 1 }}</td>
                            <td><b>{{ $r['name'] }}</b><div class="ta-muted" style="font-size:.6875rem;">NISN {{ $r['nisn'] }}</div></td>
                            <td class="ta-muted">{{ $r['class'] }}</td>
                            <td class="ta-muted">{{ ucfirst($r['student_status']) }}</td>
                            <td class="r">{{ $rp($r['due']) }}</td>
                            <td class="r">{{ $rp($r['paid']) }}</td>
                            <td class="r ta-bad">{{ $rp($r['remaining']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
