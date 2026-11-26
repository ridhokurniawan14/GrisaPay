@php $mb = fn (int $b): string => number_format($b / 1048576, 2, ',', '.').' MB'; @endphp
<x-filament-panels::page>
    <style>
        .bk { display: grid; gap: 1rem; }
        .bk-card { border-radius: 1rem; padding: 1rem 1.125rem; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / .05), 0 0 0 1px var(--gray-200); }
        .dark .bk-card { background: var(--gray-900); box-shadow: 0 0 0 1px var(--gray-800); }
        .bk-title { font-weight: 600; margin-bottom: .5rem; }
        .bk-muted { color: var(--gray-500); font-size: .8125rem; }
        .bk-ok { color: var(--success-600); font-weight: 600; } .dark .bk-ok { color: var(--success-400); }
        .bk-bad { color: var(--danger-600); font-weight: 600; } .dark .bk-bad { color: var(--danger-400); }
        .bk-table { width: 100%; border-collapse: collapse; font-size: .8125rem; font-variant-numeric: tabular-nums; }
        .bk-table th { text-align: left; font-size: .6875rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); padding: .25rem .5rem; }
        .bk-table td { padding: .5rem; border-top: 1px solid var(--gray-100); vertical-align: middle; }
        .dark .bk-table td { border-top-color: var(--gray-800); }
        .bk-act { display: flex; gap: .375rem; justify-content: flex-end; flex-wrap: wrap; }
        .bk-tag { font-size: .6875rem; padding: .0625rem .5rem; border-radius: 9999px; background: var(--gray-100); color: var(--gray-700); }
        .dark .bk-tag { background: var(--gray-800); color: var(--gray-300); }
        .bk code { font-size: .75rem; background: var(--gray-100); padding: .0625rem .375rem; border-radius: .375rem; } .dark .bk code { background: var(--gray-800); }
    </style>

    <div class="bk">
        <div class="bk-card">
            <div class="bk-title">Status alat cadangan</div>
            @if ($status['ok'])
                <div class="bk-ok">✓ Siap — {{ $status['version'] }}</div>
            @else
                <div class="bk-bad">✗ Belum siap: {{ $status['error'] }}</div>
                <div class="bk-muted" style="margin-top:.375rem;">Tambahkan di <code>.env</code>: <code>PG_BIN_PATH="C:\Program Files\PostgreSQL\18\bin"</code> (sesuaikan folder bin PostgreSQL Anda), lalu <code>php artisan config:clear</code>.</div>
            @endif
            <div class="bk-muted" style="margin-top:.5rem;">Lokasi cadangan: <code>{{ $dir }}</code> · Cadangan otomatis harian (label <b>auto</b>) disimpan 14 terakhir bila jadwal dipasang. Salin folder ini ke tempat aman (mis. Google Drive).</div>
        </div>

        <div class="bk-card">
            <div class="bk-title">Daftar cadangan ({{ count($backups) }})</div>
            @if (empty($backups))
                <div class="bk-muted">Belum ada cadangan. Klik “Buat cadangan sekarang”.</div>
            @else
                <div style="overflow:auto;">
                <table class="bk-table">
                    <thead><tr><th>Waktu</th><th>Jenis</th><th>Ukuran</th><th>Berkas</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($backups as $b)
                            <tr>
                                <td style="white-space:nowrap;">{{ $b['at']->format('d/m/Y H:i') }} <span class="bk-muted">· {{ $b['at']->diffForHumans() }}</span></td>
                                <td><span class="bk-tag">{{ $b['label'] }}</span></td>
                                <td>{{ $mb($b['size']) }}</td>
                                <td class="bk-muted">{{ $b['name'] }}</td>
                                <td>
                                    <div class="bk-act">
                                        <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="mountAction('download', { file: '{{ $b['name'] }}' })">Unduh</x-filament::button>
                                        <x-filament::button size="xs" color="warning" icon="heroicon-o-arrow-path" wire:click="mountAction('restore', { file: '{{ $b['name'] }}' })">Pulihkan</x-filament::button>
                                        <x-filament::button size="xs" color="danger" icon="heroicon-o-trash" wire:click="mountAction('delete', { file: '{{ $b['name'] }}' })">Hapus</x-filament::button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

        <div class="bk-card">
            <div class="bk-title">Catatan penting</div>
            <ul class="bk-muted" style="list-style: disc; padding-left: 1.25rem; display:grid; gap:.25rem;">
                <li><b>Reset data transaksi</b> menghapus pembayaran/tagihan permanen. Cadangan “sebelum-reset” dibuat otomatis, jadi bisa dipulihkan lagi.</li>
                <li><b>Pulihkan</b> mengembalikan seluruh database ke kondisi saat cadangan dibuat. Dilakukan dalam satu transaksi: bila gagal, data tidak berubah.</li>
                <li>Untuk memindahkan data ke komputer lain, salin berkas <code>.dump</code> ke folder cadangan di atas, lalu pilih “Pulihkan”.</li>
            </ul>
        </div>
    </div>
</x-filament-panels::page>
