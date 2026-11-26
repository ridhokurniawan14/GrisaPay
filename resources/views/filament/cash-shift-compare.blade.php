@php
    $rp = fn (?int $n): string => $n === null ? '-' : 'Rp '.number_format($n, 0, ',', '.');
    $cols = $cols; $all = $all;
    $labels = \App\Services\CashRecap::GRADE_LABELS;
    $diffText = fn (?int $d): string => $d === null ? '-' : ($d === 0 ? 'Cocok' : ($d < 0 ? 'Kurang ' : 'Lebih ').$rp(abs($d)));
    $diffClass = fn (?int $d): string => $d === null || $d === 0 ? '' : ($d < 0 ? 'cp-bad' : 'cp-warn');
@endphp

<style>
    .cp-wrap { overflow: auto; border: 1px solid var(--gray-200); border-radius: .75rem; max-height: 65vh; }
    .dark .cp-wrap { border-color: var(--gray-700); }
    .cp { width: 100%; border-collapse: collapse; font-size: .8125rem; font-variant-numeric: tabular-nums; }
    .cp th, .cp td { padding: .45rem .75rem; text-align: right; white-space: nowrap; border-top: 1px solid var(--gray-100); }
    .dark .cp th, .dark .cp td { border-top-color: var(--gray-800); }
    .cp th:first-child, .cp td:first-child { text-align: left; position: sticky; left: 0; background: #fff; }
    .dark .cp th:first-child, .dark .cp td:first-child { background: var(--gray-900); }
    .cp thead th { position: sticky; top: 0; background: var(--gray-50); border-top: 0; z-index: 2; vertical-align: bottom; }
    .dark .cp thead th { background: var(--gray-800); }
    .cp thead th:first-child { z-index: 3; }
    .cp small { display: block; font-weight: 400; color: var(--gray-500); }
    .cp .cp-sec td { background: var(--gray-50); font-size: .6875rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); font-weight: 600; }
    .dark .cp .cp-sec td { background: var(--gray-800); }
    .cp .cp-all { background: var(--gray-50); font-weight: 700; }
    .dark .cp .cp-all { background: var(--gray-800); }
    .cp-b td { font-weight: 600; }
    .cp-bad { color: var(--danger-600); font-weight: 600; } .dark .cp-bad { color: var(--danger-400); }
    .cp-warn { color: var(--warning-600); font-weight: 600; } .dark .cp-warn { color: var(--warning-400); }
    .cp-zero { color: var(--gray-400); }
</style>

@php $v = fn (int $n): string => $n === 0 ? '<span class="cp-zero">–</span>' : e($rp($n)); @endphp

<div class="cp-wrap">
    <table class="cp">
        <thead>
            <tr>
                <th></th>
                @foreach ($cols as $c)
                    <th>{{ $c['label'] }}<small>{{ $c['when'] }}</small></th>
                @endforeach
                <th class="cp-all">Gabungan<small>{{ $all['when'] }}</small></th>
            </tr>
        </thead>
        <tbody>
            <tr class="cp-sec"><td colspan="{{ count($cols) + 2 }}">Uang masuk per metode (sah)</td></tr>
            @foreach (['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS'] as $k => $label)
                <tr><td>{{ $label }}</td>@foreach ($cols as $c)<td>{!! $v($c[$k]) !!}</td>@endforeach<td class="cp-all">{!! $v($all[$k]) !!}</td></tr>
            @endforeach
            <tr class="cp-b"><td>Total · transaksi</td>@foreach ($cols as $c)<td>{{ $rp($c['total']) }}<small>{{ $c['count'] }} trx</small></td>@endforeach<td class="cp-all">{{ $rp($all['total']) }}<small>{{ $all['count'] }} trx</small></td></tr>

            <tr class="cp-sec"><td colspan="{{ count($cols) + 2 }}">Uang masuk per tingkat</td></tr>
            @foreach ($labels as $g => $label)
                @if ($g !== 0 || $all['grades'][$g] > 0)
                    <tr><td>{{ $label }}</td>@foreach ($cols as $c)<td>{!! $v($c['grades'][$g]) !!}</td>@endforeach<td class="cp-all">{!! $v($all['grades'][$g]) !!}</td></tr>
                @endif
            @endforeach

            @if (! empty($classNames))
                <tr class="cp-sec"><td colspan="{{ count($cols) + 2 }}">Uang masuk per kelas</td></tr>
                @foreach ($classNames as $n)
                    <tr><td>{{ $n }}</td>@foreach ($cols as $c)<td>{!! $v($c['classes'][$n] ?? 0) !!}</td>@endforeach<td class="cp-all">{!! $v($all['classes'][$n] ?? 0) !!}</td></tr>
                @endforeach
            @endif

            <tr class="cp-sec"><td colspan="{{ count($cols) + 2 }}">Setoran tunai</td></tr>
            <tr><td>Modal awal</td>@foreach ($cols as $c)<td>{{ $rp($c['opening']) }}</td>@endforeach<td class="cp-all">{{ $rp($all['opening']) }}</td></tr>
            <tr class="cp-b"><td>Harus disetor</td>@foreach ($cols as $c)<td>{{ $rp($c['expected']) }}</td>@endforeach<td class="cp-all">{{ $rp($all['expected']) }}</td></tr>
            <tr><td>Dihitung kasir</td>@foreach ($cols as $c)<td>{{ $rp($c['counted']) }}</td>@endforeach<td class="cp-all">-</td></tr>
            <tr><td>Selisih</td>@foreach ($cols as $c)<td class="{{ $diffClass($c['diff']) }}">{{ $diffText($c['diff']) }}</td>@endforeach<td class="cp-all">{{ $rp(array_sum(array_map(fn ($c) => (int) $c['diff'], $cols))) }}</td></tr>
        </tbody>
    </table>
</div>
<div style="font-size:.75rem; color: var(--gray-500); margin-top:.5rem;">Hanya pembayaran berstatus Sah yang dihitung. Untuk melihat siswa per tingkat/kelas, buka "Rincian" pada shift yang bersangkutan.</div>
