@php
    $totals = $report->totals();
    $cols = $report->columns();
    $nCols = count($cols);
    $rp = fn ($n): string => (int) $n === 0 ? '-' : number_format((int) $n, 0, ',', '.');
    $fs = $nCols > 18 ? '6' : ($nCols > 13 ? '6.5' : '7.5');
    $moneyCount = count($cols) - count($report->lead);
    $moneyW = $moneyCount > 0 ? round(76 / $moneyCount, 2) : 10;
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $report->title }}</title>
<style>
    @page { margin: 28px 24px 44px 24px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: {{ $fs }}pt; color: #111827; }
    .kop { width: 100%; border-bottom: 2px solid {{ $brand['color'] }}; padding-bottom: 6px; margin-bottom: 8px; }
    .kop td { vertical-align: middle; }
    .logo { width: 46px; height: 46px; }
    .inst { font-size: 13pt; font-weight: bold; }
    .addr { font-size: 7.5pt; color: #6B7280; }
    h1 { font-size: 12pt; margin: 6px 0 2px 0; text-align: center; }
    .sub { font-size: 8pt; color: #4B5563; margin: 0 0 1px 0; text-align: center; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: fixed; }
    table.data th { background: {{ $brand['color'] }}; color: {{ $onPrimary }}; font-weight: bold; padding: 4px 2px; border: 1px solid #FFFFFF; text-align: center; text-transform: uppercase; }
    table.data td { padding: 3px 3px; border: 1px solid #D1D5DB; }
    table.data tbody tr:nth-child(even) td { background: #F3F4F6; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .r { text-align: right; } .c { text-align: center; } .l { text-align: left; }
    tr.total td { background: #E5E7EB !important; font-weight: bold; border-top: 2px solid #111827; }
    .empty { text-align: center; padding: 24px; color: #6B7280; }
    .ttd { width: 100%; margin-top: 22px; page-break-inside: avoid; }
    .ttd td { vertical-align: top; }
    .foot { font-size: 7pt; color: #6B7280; margin-top: 10px; font-style: italic; }
</style>
</head>
<body>
    <table class="kop">
        <tr>
            @if ($brand['logo'])
                <td style="width: 54px;"><img class="logo" src="{{ $brand['logo'] }}" alt=""></td>
            @endif
            <td style="text-align: center;">
                <div class="inst">{{ $brand['name'] }}</div>
                @if ($brand['address'] !== '' || $brand['phone'] !== '')
                    <div class="addr">{{ $brand['address'] }}@if ($brand['phone'] !== '') · Telp. {{ $brand['phone'] }}@endif</div>
                @endif
            </td>
        </tr>
    </table>

    <h1>{{ $report->title }}</h1>
    @foreach ($report->subtitle as $line)
        <p class="sub">{{ $line }}</p>
    @endforeach

    @if ($report->rows === [])
        <div class="empty">Tidak ada data untuk filter ini.</div>
    @else
        <table class="data">
            <colgroup>
                @foreach ($report->lead as $c)
                    <col style="width: {{ ($c['key'] ?? '') === 'date' ? 7 : (($c['key'] ?? '') === 'note' ? 9 : 8) }}%;">
                @endforeach
                @for ($i = 0; $i < $moneyCount; $i++)
                    <col style="width: {{ $moneyW }}%;">
                @endfor
            </colgroup>
            <thead>
                <tr>
                    @foreach ($report->lead as $c)
                        <th rowspan="2">{{ $c['label'] }}</th>
                    @endforeach
                    @foreach ($report->groups as $g)
                        @if (count($g['columns']) === 1)
                            <th rowspan="2">{{ $g['label'] }}</th>
                        @else
                            <th colspan="{{ count($g['columns']) }}">{{ $g['label'] }}</th>
                        @endif
                    @endforeach
                </tr>
                <tr>
                    @foreach ($report->groups as $g)
                        @if (count($g['columns']) > 1)
                            @foreach ($g['columns'] as $c)
                                <th>{{ $c['label'] }}</th>
                            @endforeach
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($report->rows as $row)
                    <tr>
                        @foreach ($cols as $col)
                            @php
                                $type = $col['type'] ?? 'text';
                                $align = match ($col['align'] ?? null) { 'right' => 'r', 'center' => 'c', 'left' => 'l', default => $type === 'money' ? 'r' : ($type === 'int' ? 'c' : 'l') };
                            @endphp
                            <td class="{{ $align }}">{{ $type === 'money' ? $rp($row[$col['key']] ?? 0) : ($row[$col['key']] ?? '') }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="total">
                    @foreach ($cols as $i => $col)
                        @if (isset($totals[$col['key']]))
                            <td class="r">{{ $rp($totals[$col['key']]) }}</td>
                        @else
                            <td class="{{ $i === 0 ? 'c' : '' }}">{{ $i === 0 ? 'TOTAL' : '' }}</td>
                        @endif
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endif

    @if ($report->signers !== [])
        @php $w = round(100 / count($report->signers), 2); $lastIdx = count($report->signers) - 1; @endphp
        <table class="ttd">
            <tr>
                @foreach ($report->signers as $k => $sg)
                    <td style="width: {{ $w }}%; text-align: center;">
                        @if ($k === $lastIdx)
                            {{ $brand['city'] !== '' ? $brand['city'].', ' : '' }}{{ $meta['generated_date'] }}
                        @else
                            &nbsp;
                        @endif
                        <br>{{ $sg['role'] }}
                        <br><br><br><br>
                        <strong><u>{{ $sg['name'] !== '' ? $sg['name'] : '............................' }}</u></strong>
                        @if (($sg['nip'] ?? '') !== '')<br>NIP. {{ $sg['nip'] }}@endif
                    </td>
                @endforeach
            </tr>
        </table>
    @endif
    <div class="foot">Dicetak oleh {{ $meta['generated_by'] }} pada {{ $meta['generated_at'] }}</div>
</body>
</html>
