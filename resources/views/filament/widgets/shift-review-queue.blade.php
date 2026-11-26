@php $rp = fn (?int $n): string => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Shift menunggu tinjauan" description="Setoran kasir yang sudah ditutup">
        @forelse ($shifts as $s)
            @php $d = (int) $s->difference; @endphp
            <a href="{{ $url }}" wire:navigate style="display:flex; justify-content:space-between; gap:.75rem; padding:.5rem 0; border-top:1px solid var(--gray-100); text-decoration:none; color:inherit; font-size:.8125rem;">
                <span><b>{{ $s->user?->name ?? '-' }}</b><br><span style="color: var(--gray-500);">{{ $s->opened_at->format('d/m H:i') }}–{{ $s->closed_at?->format('H:i') }} WIB</span></span>
                <span style="text-align:right; font-variant-numeric: tabular-nums;">
                    {{ $rp($s->counted_cash) }}<br>
                    <span style="font-weight:600; color: {{ $d === 0 ? 'var(--success-600)' : ($d < 0 ? 'var(--danger-600)' : 'var(--warning-600)') }};">
                        {{ $d === 0 ? 'Cocok' : ($d < 0 ? 'Kurang ' : 'Lebih ').$rp(abs($d)) }}
                    </span>
                </span>
            </a>
        @empty
            <div style="color: var(--gray-500); font-size:.875rem;">Semua shift sudah ditinjau.</div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
