<div>
    @if ($allowed)
        <style>
            .sg { display: flex; align-items: center; gap: .625rem; }
            .sg-info { display: none; text-align: right; font-size: .6875rem; line-height: 1.2; color: var(--gray-500); }
            @media (min-width: 1024px) { .sg-info { display: block; } }
            .sg-warn { color: var(--warning-600); font-weight: 600; }
            .dark .sg-warn { color: var(--warning-400); }
            .sg-bad { color: var(--danger-600); font-weight: 600; }
            .dark .sg-bad { color: var(--danger-400); }
            .sg-dot { display: inline-block; width: .5rem; height: .5rem; border-radius: 9999px; margin-right: .25rem; background: var(--success-500); }
            .sg-dot.warn { background: var(--warning-500); }
            .sg-dot.bad { background: var(--danger-500); }
        </style>

        <div class="sg">
            <div class="sg-info">
                @if ($state === 'never')
                    <div class="sg-warn"><span class="sg-dot warn"></span>Belum pernah sinkron</div>
                @elseif ($state === 'failed')
                    <div class="sg-bad"><span class="sg-dot bad"></span>Sinkron terakhir gagal</div>
                @elseif ($state === 'stale')
                    <div class="sg-warn"><span class="sg-dot warn"></span>Sudah lama tidak sinkron</div>
                @else
                    <div><span class="sg-dot"></span>Data GrisaID terbaru</div>
                @endif
                @if ($lastSuccess)
                    <div>{{ $lastSuccess->finished_at->locale('id')->translatedFormat('d M Y H:i') }} WIB · {{ $lastSuccess->finished_at->locale('id')->diffForHumans() }}</div>
                @endif
            </div>

            <x-filament::button
                size="sm"
                color="{{ in_array($state, ['never', 'stale', 'failed'], true) ? 'warning' : 'gray' }}"
                icon="heroicon-o-arrow-path"
                wire:click="sync"
                wire:loading.attr="disabled"
                wire:target="sync"
            >
                <span wire:loading.remove wire:target="sync">Sinkron GrisaID</span>
                <span wire:loading wire:target="sync">Menyinkronkan…</span>
            </x-filament::button>
        </div>
    @endif
</div>
