@php $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.'); @endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Pengeluaran terbaru" description="6 catatan terakhir (sah)">
        <x-slot name="afterHeader">
            <x-filament::link :href="$url" wire:navigate size="sm">Semua →</x-filament::link>
        </x-slot>
        @forelse ($rows as $e)
            <div style="display:flex; justify-content:space-between; gap:.75rem; padding:.5rem 0; border-top:1px solid var(--gray-100); font-size:.8125rem;">
                <span>
                    <b>{{ $e->description }}</b><br>
                    <span style="color: var(--gray-500); font-size:.6875rem;">{{ $e->entry_date->format('d/m/Y') }} · {{ $e->category?->name ?? 'Tanpa kategori' }} · {{ $e->account === 'kas' ? 'Kas' : 'Bank' }}{{ $e->party ? ' · '.$e->party : '' }}</span>
                </span>
                <span style="text-align:right; font-variant-numeric: tabular-nums; font-weight:600; white-space:nowrap; color: var(--danger-600);">− {{ $rp($e->amount) }}</span>
            </div>
        @empty
            <div style="color: var(--gray-500); font-size:.875rem;">Belum ada pengeluaran.</div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
