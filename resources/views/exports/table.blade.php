@php
    $cols = $report->pdfColumns();
    $totals = $report->totals();
    $rp = fn ($n): string => (int) $n === 0 ? '-' : number_format((int) $n, 0, ',', '.');
    $badge = [
        'Disetujui' => ['#DCFCE7', '#166534'],
        'Ditolak' => ['#FEE2E2', '#991B1B'],
        'Dicabut' => ['#E5E7EB', '#374151'],
        'Diajukan' => ['#FEF3C7', '#92400E'],
    ];
    $small = count($cols) > 11;
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $report->title }}</title>
<style>
    @page { margin: 28px 30px 44px 30px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: {{ $small ? '7' : '8' }}pt; color: #111827; }
    .kop { width: 100%; border-bottom: 2px solid {{ $brand['color'] }}; padding-bottom: 6px; margin-bottom: 8px; }
    .kop td { vertical-align: middle; }
    .logo { width: 46px; height: 46px; }
    .inst { font-size: 13pt; font-weight: bold; }
    .addr { font-size: 7.5pt; color: #6B7280; }
    h1 { font-size: 12pt; margin: 6px 0 2px 0; text-align: center; }
    .sub { font-size: 8pt; color: #4B5563; margin: 0 0 1px 0; text-align: center; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.data th { background: {{ $brand['color'] }}; color: {{ $onPrimary }}; font-weight: bold; padding: 5px 4px; border: 1px solid {{ $brand['color'] }}; text-align: center; }
    table.data td { padding: 3.5px 4px; border: 1px solid #D1D5DB; }
    table.data tr:nth-child(even) td { background: #F3F4F6; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .r { text-align: right; } .c { text-align: center; } .l { text-align: left; }
    .badge { padding: 1px 5px; border-radius: 8px; font-weight: bold; font-size: 7pt; }
    tr.total td { background: #E5E7EB !important; font-weight: bold; border-top: 2px solid #111827; }
    .empty { text-align: center; padding: 24px; color: #6B7280; }
    .ttd { width: 100%; margin-top: 22px; page-break-inside: avoid; }
    .ttd td { width: 50%; vertical-align: top; }
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
            <thead>
                <tr>
                    @foreach ($cols as $col)
                        <th>{{ $col['label'] }}@if (($col['type'] ?? 'text') === 'money') (Rp)@endif</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($report->rows as $row)
                    <tr>
                        @foreach ($cols as $col)
                            @php
                                $type = $col['type'] ?? 'text';
                                $value = $row[$col['key']] ?? null;
                                $align = match ($col['align'] ?? null) { 'right' => 'r', 'center' => 'c', 'left' => 'l', default => $type === 'money' ? 'r' : ($type === 'int' ? 'c' : 'l') };
                                $isStatus = $report->statusKey === $col['key'] && isset($badge[(string) $value]);
                            @endphp
                            <td class="{{ $isStatus ? 'c' : $align }}">
                                @if ($isStatus)
                                    <span class="badge" style="background: {{ $badge[$value][0] }}; color: {{ $badge[$value][1] }};">{{ $value }}</span>
                                @elseif ($type === 'money')
                                    {{ $rp($value) }}
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach

                @if ($totals !== [])
                    <tr class="total">
                        @foreach ($cols as $i => $col)
                            @if (isset($totals[$col['key']]))
                                <td class="r">{{ $rp($totals[$col['key']]) }}</td>
                            @else
                                <td class="{{ $i === 0 ? 'c' : '' }}">{{ $i === 0 ? 'TOTAL' : '' }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    <table class="ttd">
        <tr>
            <td class="foot">Dicetak oleh {{ $meta['generated_by'] }} pada {{ $meta['generated_at'] }}</td>
            <td style="text-align: center;">
                {{ $brand['city'] !== '' ? $brand['city'].', ' : '' }}{{ $meta['generated_date'] }}<br>
                Bendahara
                <br><br><br><br>
                <strong><u>{{ $brand['treasurer_name'] !== '' ? $brand['treasurer_name'] : '............................' }}</u></strong>
                @if ($brand['treasurer_nip'] !== '')<br>NIP. {{ $brand['treasurer_nip'] }}@endif
            </td>
        </tr>
    </table>
</body>
</html>
