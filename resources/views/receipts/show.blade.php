<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @page { size: {{ $paperMm }}mm auto; margin: 2mm; }
    html, body { margin: 0; padding: 0; background: #fff; color: #000; }
    pre {
        margin: 0;
        font-family: 'Courier New', Courier, monospace;
        font-size: {{ $fontPt }}pt;
        font-weight: 400;
        line-height: 1.2;
        white-space: pre;
    }
    .sheet { width: {{ max(40, $paperMm - 4) }}mm; max-width: 100%; overflow: hidden; }
    .logo { display: block; margin: 0 auto 1mm; width: 20mm; height: auto; image-rendering: pixelated; image-rendering: crisp-edges; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .screen-only { font-family: system-ui, sans-serif; font-size: 12px; padding: 8px; }
    @media print { .screen-only { display: none; } }
</style>
</head>
<body>
    <div class="screen-only">
        Jendela cetak akan terbuka otomatis. <a href="#" onclick="window.print(); return false;">Cetak lagi</a> ·
        <a href="#" onclick="window.close(); return false;">Tutup tab ini</a>
    </div>
@php
    $html = collect($lines)
        ->map(fn (string $l): string => str_starts_with($l, "\x01") ? '<b>'.e(substr($l, 1)).'</b>' : e($l))
        ->implode("\n");
@endphp
<div class="sheet">
@if (! empty($logo))
    <img class="logo" src="{{ $logo }}" alt="">
@endif
<pre>{!! $html !!}

</pre>
</div>
<script>
    window.addEventListener('load', function () {
        // Menciut otomatis bila teks lebih lebar dari kertas (mis. printer biasa/ukuran font berbeda).
        var sheet = document.querySelector('.sheet');
        var pre = sheet.querySelector('pre');
        pre.style.display = 'inline-block';
        var sw = sheet.clientWidth, pw = pre.scrollWidth;
        if (pw > sw) { pre.style.zoom = (sw / pw).toFixed(4); }
        setTimeout(function () { window.focus(); window.print(); }, 150);
    });
</script>
</body>
</html>
