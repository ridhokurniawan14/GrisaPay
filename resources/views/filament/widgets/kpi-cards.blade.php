<x-filament-widgets::widget>
    <style>
        .kp-group + .kp-group { margin-top: 1.5rem; }
        .kp-title { display: flex; align-items: center; gap: .625rem; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--gray-500); margin-bottom: .625rem; }
        .kp-title::after { content: ''; flex: 1; height: 1px; background: var(--gray-200); }
        .dark .kp-title::after { background: var(--gray-800); }
        .kp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(14.5rem, 1fr)); gap: .875rem; }
        .kp { --c: var(--primary-600); position: relative; display: flex; flex-direction: column; gap: .5rem; padding: 1rem 1.125rem; border-radius: 1rem; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / .05), 0 0 0 1px var(--gray-200); text-decoration: none; color: inherit; overflow: hidden; transition: box-shadow .15s, transform .15s; }
        .dark .kp { background: var(--gray-900); box-shadow: 0 0 0 1px var(--gray-800); --c: var(--primary-400); }
        .kp::before { content: ''; position: absolute; inset: 0 auto 0 0; width: .25rem; background: var(--c); }
        a.kp:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgb(0 0 0 / .08), 0 0 0 1px var(--c); }
        .kp-success { --c: var(--success-600); } .dark .kp-success { --c: var(--success-400); }
        .kp-primary { --c: var(--primary-600); } .dark .kp-primary { --c: var(--primary-400); }
        .kp-info { --c: var(--info-600, #0284c7); } .dark .kp-info { --c: var(--info-400, #38bdf8); }
        .kp-warning { --c: var(--warning-600); } .dark .kp-warning { --c: var(--warning-400); }
        .kp-danger { --c: var(--danger-600); } .dark .kp-danger { --c: var(--danger-400); }
        .kp-gray { --c: var(--gray-500); } .dark .kp-gray { --c: var(--gray-400); }
        .kp-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
        .kp-label { font-size: .8125rem; font-weight: 500; color: var(--gray-600); }
        .dark .kp-label { color: var(--gray-400); }
        .kp-ico { width: 2.25rem; height: 2.25rem; border-radius: .75rem; display: flex; align-items: center; justify-content: center; color: var(--c); background: color-mix(in srgb, var(--c) 14%, transparent); flex: none; }
        .kp-ico svg { width: 1.25rem; height: 1.25rem; }
        .kp-value { font-size: 1.625rem; line-height: 1.2; font-weight: 700; letter-spacing: -.01em; font-variant-numeric: tabular-nums; color: var(--gray-950); }
        .dark .kp-value { color: #fff; }
        .kp-sub { font-size: .75rem; color: var(--gray-500); line-height: 1.35; }
        .kp-row { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
        .kp-delta { display: inline-flex; align-items: center; gap: .25rem; font-size: .6875rem; font-weight: 600; padding: .125rem .5rem; border-radius: 9999px; white-space: nowrap; }
        .kp-up { color: var(--success-700); background: var(--success-100); } .dark .kp-up { color: var(--success-300); background: color-mix(in srgb, var(--success-500) 20%, transparent); }
        .kp-down { color: var(--danger-700); background: var(--danger-100); } .dark .kp-down { color: var(--danger-300); background: color-mix(in srgb, var(--danger-500) 20%, transparent); }
        .kp-note { font-size: .6875rem; color: var(--gray-500); }
        .kp-bar { height: .375rem; border-radius: 9999px; background: var(--gray-200); overflow: hidden; }
        .dark .kp-bar { background: var(--gray-800); }
        .kp-bar > div { height: 100%; background: var(--c); border-radius: 9999px; }
        .kp-spark { width: 100%; height: 2rem; display: block; color: var(--c); }
        .kp-go { font-size: .6875rem; color: var(--c); font-weight: 600; }
    </style>

    @foreach ($groups as $g)
        <div class="kp-group">
            <div class="kp-title">{{ $g['title'] }}</div>
            <div class="kp-grid">
                @foreach ($g['cards'] as $c)
                    @php
                        $tag = ! empty($c['url']) ? 'a' : 'div';
                        $max = ! empty($c['spark']) ? max(1, max($c['spark'])) : 1;
                        $pts = '';
                        if (! empty($c['spark'])) {
                            $n = count($c['spark']);
                            foreach ($c['spark'] as $i => $v) {
                                $pts .= round($i / max(1, $n - 1) * 100, 1).','.round(28 - ($v / $max) * 26, 1).' ';
                            }
                        }
                    @endphp
                    <{{ $tag }} class="kp kp-{{ $c['tone'] }}" @if (! empty($c['url'])) href="{{ $c['url'] }}" wire:navigate @endif>
                        <div class="kp-head">
                            <span class="kp-label">{{ $c['label'] }}</span>
                            <span class="kp-ico"><x-filament::icon :icon="$c['icon']" /></span>
                        </div>
                        <div class="kp-value">{{ $c['value'] }}</div>
                        @if (! empty($c['delta']))
                            <div class="kp-row">
                                <span class="kp-delta {{ $c['delta']['up'] ? 'kp-up' : 'kp-down' }}">{{ $c['delta']['up'] ? '▲' : '▼' }} {{ $c['delta']['text'] }}</span>
                                <span class="kp-note">{{ $c['deltaNote'] ?? '' }}</span>
                            </div>
                        @endif
                        @if (isset($c['progress']))
                            <div class="kp-bar"><div style="width: {{ max(0, min(100, $c['progress'])) }}%"></div></div>
                        @endif
                        @if ($pts !== '')
                            <svg class="kp-spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">
                                <polyline points="{{ trim($pts) }}" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                            </svg>
                        @endif
                        <div class="kp-sub">{{ $c['sub'] }}</div>
                    </{{ $tag }}>
                @endforeach
            </div>
        </div>
    @endforeach
</x-filament-widgets::widget>
