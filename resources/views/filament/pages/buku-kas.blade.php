@php
    $rp = fn (int $n): string => ($n < 0 ? '−' : '').'Rp '.number_format(abs($n), 0, ',', '.');
    $kindLabel = ['pembayaran' => 'Pembayaran', 'keluar' => 'Pengeluaran', 'masuk' => 'Pemasukan lain', 'mutasi' => 'Mutasi'];
@endphp
<x-filament-panels::page>
    <style>
        .bkk { display: grid; gap: 1.25rem; }
        .bkk-bar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; }
        .bkk-bar label { display: block; font-size: .6875rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .25rem; }
        .bkk-in { padding: .4rem .6rem; border-radius: .5rem; border: 1px solid var(--gray-300); background: #fff; color: inherit; font-size: .875rem; }
        .dark .bkk-in { background: var(--gray-900); border-color: var(--gray-700); }
        .bkk-chip { font: inherit; font-size: .75rem; cursor: pointer; border: 1px solid var(--gray-300); border-radius: 9999px; padding: .25rem .7rem; background: transparent; color: inherit; }
        .dark .bkk-chip { border-color: var(--gray-600); }
        .bkk-chip:hover { border-color: var(--primary-500); }
        .bkk-card { border-radius: 1rem; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / .05), 0 0 0 1px var(--gray-200); overflow: hidden; }
        .dark .bkk-card { background: var(--gray-900); box-shadow: 0 0 0 1px var(--gray-800); }
        .bkk-head { padding: .875rem 1.125rem; display: flex; flex-wrap: wrap; justify-content: space-between; gap: .75rem; align-items: center; border-bottom: 1px solid var(--gray-100); }
        .dark .bkk-head { border-bottom-color: var(--gray-800); }
        .bkk-title { font-weight: 700; font-size: 1.0625rem; }
        .bkk-sum { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .5rem 1.5rem; padding: .75rem 1.125rem; }
        .bkk-sum small { display: block; font-size: .6875rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); }
        .bkk-sum b { font-size: 1.125rem; font-variant-numeric: tabular-nums; }
        .bkk-in-c { color: var(--success-600); } .dark .bkk-in-c { color: var(--success-400); }
        .bkk-out-c { color: var(--danger-600); } .dark .bkk-out-c { color: var(--danger-400); }
        .bkk-t { width: 100%; border-collapse: collapse; font-size: .8125rem; font-variant-numeric: tabular-nums; }
        .bkk-t th { text-align: left; font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .4rem .75rem; background: var(--gray-50); white-space: nowrap; }
        .dark .bkk-t th { background: var(--gray-800); }
        .bkk-t td { padding: .45rem .75rem; border-top: 1px solid var(--gray-100); vertical-align: top; }
        .dark .bkk-t td { border-top-color: var(--gray-800); }
        .bkk-t .r { text-align: right; white-space: nowrap; }
        .bkk-muted { color: var(--gray-500); }
        .bkk-tag { font-size: .625rem; padding: .0625rem .45rem; border-radius: 9999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
        .dark .bkk-tag { background: var(--gray-800); color: var(--gray-300); }
        .bkk-warn { margin: 0 1.125rem .75rem; padding: .5rem .75rem; border-radius: .5rem; font-size: .75rem; background: color-mix(in srgb, var(--warning-500) 14%, transparent); color: var(--warning-700); }
        .dark .bkk-warn { color: var(--warning-300); }
        .bkk-x { font: inherit; font-size: .6875rem; background: none; border: 0; cursor: pointer; color: var(--danger-600); text-decoration: underline; padding: 0; }
        .dark .bkk-x { color: var(--danger-400); }
    </style>

    <div class="bkk" wire:poll.30s.visible>
        <div class="bkk-bar">
            <div><label>Dari tanggal</label><input type="date" class="bkk-in" wire:model.live="from"></div>
            <div><label>Sampai</label><input type="date" class="bkk-in" wire:model.live="until"></div>
            <div><label>Akun</label>
                <select class="bkk-in" wire:model.live="account">
                    <option value="">Kas & Bank</option>
                    <option value="kas">Kas (tunai)</option>
                    <option value="bank">Bank (transfer/QRIS)</option>
                </select>
            </div>
            <div style="display:flex; gap:.375rem; flex-wrap:wrap;">
                <button type="button" class="bkk-chip" wire:click="preset('today')">Hari ini</button>
                <button type="button" class="bkk-chip" wire:click="preset('month')">Bulan ini</button>
                <button type="button" class="bkk-chip" wire:click="preset('last_month')">Bulan lalu</button>
                <button type="button" class="bkk-chip" wire:click="preset('year')">Tahun ini</button>
            </div>
        </div>

        @foreach ($this->ledgers as $l)
            <div class="bkk-card">
                <div class="bkk-head">
                    <div class="bkk-title">{{ $l['label'] }}</div>
                    <div class="bkk-muted" style="font-size:.75rem;">
                        @if ($l['as_of']) Saldo awal diatur per {{ \Illuminate\Support\Carbon::parse($l['as_of'])->format('d/m/Y') }} @else Saldo awal belum diatur @endif
                    </div>
                </div>
                <div class="bkk-sum">
                    <div><small>Saldo awal periode</small><b>{{ $rp($l['opening']) }}</b></div>
                    <div><small>Total masuk</small><b class="bkk-in-c">+ {{ $rp($l['total_in']) }}</b></div>
                    <div><small>Total keluar</small><b class="bkk-out-c">− {{ $rp($l['total_out']) }}</b></div>
                    <div><small>Saldo akhir</small><b>{{ $rp($l['closing']) }}</b></div>
                </div>
                @if (! $l['tracked'])
                    <div class="bkk-warn">
                        {{ $l['as_of'] ? 'Periode ini dimulai sebelum tanggal saldo awal, jadi saldo dihitung dari Rp 0.' : 'Saldo awal belum diatur, jadi saldo dihitung dari Rp 0.' }}
                        @if ($this->canManage()) Atur lewat tombol “Saldo awal”. @endif
                    </div>
                @endif
                <div style="overflow:auto;">
                    <table class="bkk-t">
                        <thead><tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th>No. bukti</th><th class="r">Masuk</th><th class="r">Keluar</th><th class="r">Saldo</th><th></th></tr></thead>
                        <tbody>
                            <tr><td colspan="6" class="bkk-muted">Saldo awal periode</td><td class="r"><b>{{ $rp($l['opening']) }}</b></td><td></td></tr>
                            @forelse ($l['rows'] as $r)
                                <tr>
                                    <td style="white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($r['date'])->format('d/m/Y') }}</td>
                                    <td><span class="bkk-tag">{{ $kindLabel[$r['kind']] ?? $r['kind'] }}</span></td>
                                    <td>{{ $r['desc'] }}</td>
                                    <td class="bkk-muted">{{ $r['ref'] ?: '' }}</td>
                                    <td class="r bkk-in-c">{{ $r['in'] ? $rp($r['in']) : '' }}</td>
                                    <td class="r bkk-out-c">{{ $r['out'] ? $rp($r['out']) : '' }}</td>
                                    <td class="r"><b>{{ $rp($r['balance']) }}</b></td>
                                    <td class="r">
                                        @if ($r['entry_id'] && $this->canManage())
                                            <button type="button" class="bkk-x" wire:click="mountAction('voidEntry', { id: {{ $r['entry_id'] }} })">Batalkan</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="bkk-muted">Tidak ada pergerakan pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div class="bkk-muted" style="font-size:.75rem;">
            Masuk: pembayaran siswa berstatus Sah (tunai → Kas; transfer & QRIS → Bank), pemasukan lain, dan mutasi masuk. Keluar: pengeluaran dan mutasi keluar. Catatan yang dibatalkan tidak dihitung.
        </div>
    </div>
</x-filament-panels::page>
