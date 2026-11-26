@php
    $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $chip = fn (string $s): array => match ($s) {
        \App\Models\Payment::PAID => ['Sah', 'success'],
        \App\Models\Payment::PENDING => ['Menunggu', 'warning'],
        \App\Models\Payment::REJECTED => ['Ditolak', 'danger'],
        default => ['Void', 'danger'],
    };
@endphp
<x-filament-widgets::widget>
    <x-filament::section heading="{{ $is_today ? '10 pembayaran terbaru hari ini' : '10 pembayaran terakhir' }}" description="{{ $is_today ? '' : 'Belum ada pembayaran hari ini — menampilkan yang terakhir tercatat.' }}">
        <x-slot name="afterHeader">
            <x-filament::link :href="$url" wire:navigate size="sm">Semua pembayaran →</x-filament::link>
        </x-slot>
        <style>
            .lp { width: 100%; border-collapse: collapse; font-size: .8125rem; font-variant-numeric: tabular-nums; }
            .lp th { text-align: left; font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .25rem .625rem; white-space: nowrap; }
            .lp td { padding: .45rem .625rem; border-top: 1px solid var(--gray-100); vertical-align: top; }
            .dark .lp td { border-top-color: var(--gray-800); }
            .lp .r { text-align: right; white-space: nowrap; }
            .lp-muted { color: var(--gray-500); }
            .lp-chip { padding: .0625rem .5rem; border-radius: 9999px; font-size: .6875rem; font-weight: 600; white-space: nowrap; }
            .lp-success { background: var(--success-100); color: var(--success-700); } .dark .lp-success { background: var(--success-900); color: var(--success-300); }
            .lp-warning { background: var(--warning-100); color: var(--warning-700); } .dark .lp-warning { background: var(--warning-900); color: var(--warning-300); }
            .lp-danger { background: var(--danger-100); color: var(--danger-700); } .dark .lp-danger { background: var(--danger-900); color: var(--danger-300); }
        </style>
        @if (empty($rows))
            <div class="lp-muted" style="font-size:.875rem;">Belum ada pembayaran.</div>
        @else
            <div style="overflow:auto;">
            <table class="lp">
                <thead><tr><th>Waktu</th><th>Kwitansi</th><th>Siswa</th><th>Kelas</th><th>Untuk</th><th>Metode</th><th class="r">Jumlah</th><th>Status</th><th>Kasir</th></tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        @php [$label, $color] = $chip($r['status']); @endphp
                        <tr>
                            <td style="white-space:nowrap;">{{ $r['when'] }}</td>
                            <td>{{ $r['receipt'] }}</td>
                            <td><b>{{ $r['student'] }}</b></td>
                            <td class="lp-muted">{{ $r['class'] }}</td>
                            <td class="lp-muted">{{ $r['for'] ?: '-' }}</td>
                            <td>{{ $r['method'] }}</td>
                            <td class="r"><b>{{ $rp($r['amount']) }}</b></td>
                            <td><span class="lp-chip lp-{{ $color }}">{{ $label }}</span></td>
                            <td>{{ $r['creator'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
