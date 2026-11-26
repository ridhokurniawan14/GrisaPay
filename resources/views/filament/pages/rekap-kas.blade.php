@php
    $r = $this->recap;
    $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $canRange = $this->canRange();
    $day = fn (string $d): string => \Illuminate\Support\Carbon::parse($d)->locale('id')->translatedFormat('l, d M Y');
@endphp

<x-filament-panels::page>
    <style>
        .rk { display: grid; gap: 1rem; font-size: .875rem; }
        .rk-muted { color: var(--gray-500); }
        .rk-card { border: 1px solid var(--gray-200); border-radius: .75rem; background: #fff; overflow: hidden; }
        .dark .rk-card { border-color: var(--gray-700); background: var(--gray-900); }
        .rk-pad { padding: .875rem 1rem; }
        .rk-head { padding: .625rem 1rem; font-weight: 600; background: var(--gray-50); border-bottom: 1px solid var(--gray-200); }
        .dark .rk-head { background: var(--gray-800); border-color: var(--gray-700); }
        .rk-filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; }
        .rk-field label { display: block; font-size: .6875rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .25rem; }
        .rk-input { border: 1px solid var(--gray-300); border-radius: .5rem; padding: .4rem .625rem; background: #fff; color: var(--gray-950); min-width: 9rem; }
        .dark .rk-input { border-color: var(--gray-600); background-color: var(--gray-900); color: var(--gray-100); color-scheme: dark; }
        .rk-presets { display: flex; flex-wrap: wrap; gap: .375rem; }
        .rk-chip { border: 1px solid var(--gray-300); border-radius: 9999px; padding: .25rem .75rem; font-size: .75rem; cursor: pointer; background: transparent; color: inherit; }
        .rk-chip:hover { background: var(--gray-100); }
        .dark .rk-chip { border-color: var(--gray-600); }
        .dark .rk-chip:hover { background: var(--gray-800); }
        .rk-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr)); gap: .75rem; }
        .rk-stat { border: 1px solid var(--gray-200); border-radius: .75rem; padding: .75rem 1rem; background: #fff; }
        .dark .rk-stat { border-color: var(--gray-700); background: var(--gray-900); }
        .rk-stat small { display: block; font-size: .6875rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); }
        .rk-stat b { display: block; font-size: 1.125rem; font-variant-numeric: tabular-nums; margin-top: .125rem; }
        .rk-stat span { font-size: .75rem; color: var(--gray-500); }
        .rk-click { cursor: pointer; text-align: left; width: 100%; transition: border-color .15s, box-shadow .15s; }
        .rk-click:hover { border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500); }
        .rk-row-click { cursor: pointer; }
        .rk-row-click:hover td { background: var(--gray-50); }
        .dark .rk-row-click:hover td { background: var(--gray-800); }
        .rk-main { background: var(--primary-50); border-color: var(--primary-200); }
        .dark .rk-main { background: color-mix(in srgb, var(--primary-500) 14%, var(--gray-900)); border-color: var(--primary-700); }
        .rk-warn b { color: var(--warning-600); } .dark .rk-warn b { color: var(--warning-400); }
        .rk-bad b { color: var(--danger-600); } .dark .rk-bad b { color: var(--danger-400); }
        .rk-grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); gap: 1rem; }
        .rk-scroll { max-height: 22rem; overflow: auto; }
        .rk-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .rk-table th { position: sticky; top: 0; background: var(--gray-50); text-align: left; font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .4rem 1rem; }
        .dark .rk-table th { background: var(--gray-800); }
        .rk-table td { padding: .4rem 1rem; border-top: 1px solid var(--gray-100); }
        .dark .rk-table td { border-top-color: var(--gray-800); }
        .rk-r { text-align: right !important; }
        .rk-foot td { font-weight: 700; border-top: 2px solid var(--gray-300); }
        .dark .rk-foot td { border-top-color: var(--gray-600); }
    </style>

    <div class="rk" wire:poll.30s.visible>
        <div class="rk-card rk-pad">
            <div class="rk-filters">
                <div class="rk-field">
                    <label>{{ $canRange ? 'Dari tanggal' : 'Tanggal' }}</label>
                    <input type="date" class="rk-input" wire:model.live="from">
                </div>
                @if ($canRange)
                    <div class="rk-field">
                        <label>Sampai tanggal</label>
                        <input type="date" class="rk-input" wire:model.live="until">
                    </div>
                @endif
                <div class="rk-field">
                    <label>Metode</label>
                    <select class="rk-input" wire:model.live="method">
                        <option value="">Semua metode</option>
                        @foreach (\App\Models\Payment::METHODS as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="rk-field">
                    <label>Kasir</label>
                    <select class="rk-input" wire:model.live="cashier">
                        <option value="">Semua kasir</option>
                        @foreach ($this->cashiers as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="rk-presets" style="margin-top:.75rem;">
                <button type="button" class="rk-chip" wire:click="preset('today')">Hari ini</button>
                <button type="button" class="rk-chip" wire:click="preset('yesterday')">Kemarin</button>
                @if ($canRange)
                    <button type="button" class="rk-chip" wire:click="preset('week')">7 hari terakhir</button>
                    <button type="button" class="rk-chip" wire:click="preset('month')">Bulan ini</button>
                    <button type="button" class="rk-chip" wire:click="preset('lastmonth')">Bulan lalu</button>
                    <button type="button" class="rk-chip" wire:click="preset('year')">Tahun ini</button>
                @endif
            </div>
        </div>

        <div class="rk-stats">
            <button type="button" class="rk-stat rk-main rk-click" wire:click="mountAction('detail', { key: 'total' })"><small>Total diterima (Sah)</small><b>{{ $rp($r['total']) }}</b><span>{{ $r['count'] }} transaksi · klik untuk rincian</span></button>
            @foreach ($r['by_method'] as $mKey => $m)
                <button type="button" class="rk-stat rk-click" wire:click="mountAction('detail', { key: 'method:{{ $mKey }}' })"><small>{{ $m['label'] }}</small><b>{{ $rp($m['total']) }}</b><span>{{ $m['count'] }} transaksi</span></button>
            @endforeach
            <button type="button" class="rk-stat rk-warn rk-click" wire:click="mountAction('detail', { key: 'pending' })"><small>Menunggu verifikasi</small><b>{{ $r['pending']['count'] }}</b><span>{{ $rp($r['pending']['total']) }} · belum dihitung</span></button>
            <button type="button" class="rk-stat rk-bad rk-click" wire:click="mountAction('detail', { key: 'void' })"><small>Dibatalkan (void)</small><b>{{ $r['void']['count'] }}</b><span>{{ $rp($r['void']['total']) }} · tidak dihitung</span></button>
        </div>

        <div class="rk-stats">
            @foreach ($r['by_grade'] as $g)
                @if ($g['grade'] !== 0 || $g['total'] > 0)
                    <button type="button" class="rk-stat rk-click" wire:click="mountAction('detail', { key: 'grade:{{ $g['grade'] }}' })"><small>{{ $g['label'] }}</small><b>{{ $rp($g['total']) }}</b><span>{{ $g['students'] }} siswa · {{ $g['count'] }} transaksi</span></button>
                @endif
            @endforeach
        </div>

        <div class="rk-card">
            <div class="rk-head">Per kelas</div>
            @if (empty($r['by_class']))
                <div class="rk-pad rk-muted">Belum ada penerimaan.</div>
            @else
                <div class="rk-scroll"><table class="rk-table">
                    <thead><tr><th>Tingkat</th><th>Kelas</th><th class="rk-r">Siswa membayar</th><th class="rk-r">Transaksi</th><th class="rk-r">Total</th></tr></thead>
                    <tbody>
                        @foreach ($r['by_class'] as $c)
                            <tr class="rk-row-click" wire:click="mountAction('detail', { key: 'class:{{ rawurlencode($c['name']) }}' })"><td class="rk-muted">{{ $c['label'] }}</td><td>{{ $c['name'] }}</td><td class="rk-r">{{ $c['students'] }}</td><td class="rk-r">{{ $c['count'] }}</td><td class="rk-r">{{ $rp($c['total']) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="rk-foot"><td colspan="4">Jumlah seluruh kelas</td><td class="rk-r">{{ $rp($r['total']) }}</td></tr></tfoot>
                </table></div>
            @endif
        </div>

        <div class="rk-grid2">
            <div class="rk-card">
                <div class="rk-head">Per kategori</div>
                @if (empty($r['by_category']))
                    <div class="rk-pad rk-muted">Belum ada penerimaan.</div>
                @else
                    <div class="rk-scroll"><table class="rk-table">
                        <thead><tr><th>Kategori</th><th class="rk-r">Rincian</th><th class="rk-r">Total</th></tr></thead>
                        <tbody>
                            @foreach ($r['by_category'] as $c)
                                <tr><td>{{ $c['name'] }}</td><td class="rk-r">{{ $c['count'] }}</td><td class="rk-r">{{ $rp($c['total']) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr class="rk-foot"><td colspan="2">Jumlah</td><td class="rk-r">{{ $rp(array_sum(array_column($r['by_category'], 'total'))) }}</td></tr></tfoot>
                    </table></div>
                @endif
            </div>

            <div class="rk-card">
                <div class="rk-head">Per kasir</div>
                @if (empty($r['by_cashier']))
                    <div class="rk-pad rk-muted">Belum ada penerimaan.</div>
                @else
                    <div class="rk-scroll"><table class="rk-table">
                        <thead><tr><th>Kasir</th><th class="rk-r">Transaksi</th><th class="rk-r">Total</th></tr></thead>
                        <tbody>
                            @foreach ($r['by_cashier'] as $c)
                                <tr><td>{{ $c['name'] }}</td><td class="rk-r">{{ $c['count'] }}</td><td class="rk-r">{{ $rp($c['total']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        </div>

        <div class="rk-card">
            <div class="rk-head">Per rincian tagihan</div>
            @if (empty($r['by_item']))
                <div class="rk-pad rk-muted">Belum ada penerimaan.</div>
            @else
                <div class="rk-scroll"><table class="rk-table">
                    <thead><tr><th>Kategori</th><th>Rincian</th><th>TA</th><th class="rk-r">Transaksi</th><th class="rk-r">Total</th></tr></thead>
                    <tbody>
                        @php $lastCat = null; @endphp
                        @foreach ($r['by_item'] as $it)
                            <tr>
                                <td style="font-weight: {{ $lastCat === $it['category'] ? '400' : '600' }}; color: {{ $lastCat === $it['category'] ? 'var(--gray-500)' : 'inherit' }};">{{ $it['category'] }}</td>
                                <td>{{ $it['name'] }}</td>
                                <td class="rk-muted">{{ $it['year'] }}</td>
                                <td class="rk-r">{{ $it['count'] }}</td>
                                <td class="rk-r">{{ $rp($it['total']) }}</td>
                            </tr>
                            @php $lastCat = $it['category']; @endphp
                        @endforeach
                    </tbody>
                    <tfoot><tr class="rk-foot"><td colspan="4">Jumlah</td><td class="rk-r">{{ $rp(array_sum(array_column($r['by_item'], 'total'))) }}</td></tr></tfoot>
                </table></div>
            @endif
        </div>

        <div class="rk-card">
            <div class="rk-head">Per hari</div>
            @if (empty($r['by_day']))
                <div class="rk-pad rk-muted">Tidak ada penerimaan pada periode ini.</div>
            @else
                <div class="rk-scroll"><table class="rk-table">
                    <thead><tr><th>Tanggal</th><th class="rk-r">Transaksi</th><th class="rk-r">Tunai</th><th class="rk-r">Transfer</th><th class="rk-r">QRIS</th><th class="rk-r">Total</th></tr></thead>
                    <tbody>
                        @foreach ($r['by_day'] as $d)
                            <tr class="rk-row-click" wire:click="mountAction('detail', { key: 'day:{{ $d['date'] }}' })">
                                <td>{{ $day($d['date']) }}</td><td class="rk-r">{{ $d['count'] }}</td>
                                <td class="rk-r">{{ $rp($d['cash']) }}</td><td class="rk-r">{{ $rp($d['transfer']) }}</td><td class="rk-r">{{ $rp($d['qris']) }}</td>
                                <td class="rk-r" style="font-weight:600;">{{ $rp($d['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="rk-foot">
                        <td>Jumlah</td><td class="rk-r">{{ $r['count'] }}</td>
                        <td class="rk-r">{{ $rp($r['by_method']['cash']['total'] ?? 0) }}</td>
                        <td class="rk-r">{{ $rp($r['by_method']['transfer']['total'] ?? 0) }}</td>
                        <td class="rk-r">{{ $rp($r['by_method']['qris']['total'] ?? 0) }}</td>
                        <td class="rk-r">{{ $rp($r['total']) }}</td>
                    </tr></tfoot>
                </table></div>
            @endif
        </div>

        <div class="rk-muted" style="font-size:.75rem;">Dihitung berdasarkan waktu bayar. Pembayaran transfer/QRIS baru masuk rekap setelah diverifikasi. Untuk detail per transaksi (lengkap dengan rincian yang dibayar), gunakan unduhan "Rincian transaksi".</div>
    </div>
</x-filament-panels::page>
