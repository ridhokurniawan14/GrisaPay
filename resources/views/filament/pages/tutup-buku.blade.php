<x-filament-panels::page>
    @php
        $muted = 'color: var(--gray-500)';
        $label = 'font-size: .75rem; color: var(--gray-500); margin-bottom: .125rem';
        $val = 'font-weight: 600; font-size: .95rem';
        $grid = 'display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem';
        $nextActive = $years->first(fn ($r) => $r['model']->is_active)['model'] ?? null;
    @endphp

    <div style="display: flex; flex-direction: column; gap: 1.25rem">

        {{-- Panduan kapan tutup buku --}}
        <x-filament::section icon="heroicon-o-information-circle" icon-color="info" :compact="true">
            <x-slot name="heading">Kapan tutup buku dilakukan?</x-slot>
            <ol style="margin: 0; padding-left: 1.25rem; display: flex; flex-direction: column; gap: .375rem; font-size: .875rem; {{ $muted }}">
                <li><b>Setelah tahun ajaran baru aktif</b> di GrisaID dan sudah disinkronkan ke GrisaPay (tahun yang masih aktif tidak bisa ditutup).</li>
                <li>Biasanya <b>akhir Juli – Agustus</b>, setelah Naik Kelas dan tagihan tahun baru dibuat.</li>
                <li>Semua <b>shift kas sudah ditutup</b> dan pembayaran transfer/QRIS <b>sudah diverifikasi</b>.</li>
                <li><b>Saldo Buku Kas dicocokkan</b> dengan uang fisik dan rekening.</li>
                <li><b>Buat cadangan data</b> dulu (Pengaturan → Cadangan Data).</li>
                <li>Tutup buku <b>berurutan dari tahun tertua</b>. Sisa tunggakan tetap bisa dibayar sesudahnya.</li>
            </ol>
        </x-filament::section>

        @foreach ($years as $row)
            @php
                $y = $row['model'];
                $s = $row['live'] ?? ($row['check']['summary'] ?? null);
                $empty = ! $row['closed'] && ! $y->is_active && ($s['bills'] ?? 0) === 0;
                $isNext = $closableId === $y->id;
                $due = ! $row['closed'] && ! $y->is_active && $nextActive && $y->end_date && $y->end_date->isPast();
            @endphp

            <x-filament::section :compact="true">
                <x-slot name="heading">
                    <span style="display: inline-flex; align-items: center; gap: .5rem; flex-wrap: wrap">
                        {{ $y->name }}
                        @if ($y->is_active)
                            <x-filament::badge color="success">Aktif</x-filament::badge>
                        @elseif ($row['closed'])
                            <x-filament::badge color="danger" icon="heroicon-m-lock-closed">Ditutup</x-filament::badge>
                        @elseif ($empty)
                            <x-filament::badge color="gray">Tanpa tagihan</x-filament::badge>
                        @elseif ($isNext)
                            <x-filament::badge color="warning">Waktunya ditutup</x-filament::badge>
                        @else
                            <x-filament::badge color="gray">Belum ditutup</x-filament::badge>
                        @endif
                    </span>
                </x-slot>

                @if ($s && ! $empty)
                    <div style="{{ $grid }}">
                        <div><div style="{{ $label }}">Tagihan</div><div style="{{ $val }}">Rp {{ number_format($s['billed'], 0, ',', '.') }}</div></div>
                        <div><div style="{{ $label }}">Terbayar</div><div style="{{ $val }}">Rp {{ number_format($s['paid'], 0, ',', '.') }}</div></div>
                        <div><div style="{{ $label }}">Sisa tunggakan</div><div style="{{ $val }}">Rp {{ number_format($s['outstanding'], 0, ',', '.') }}</div></div>
                        <div><div style="{{ $label }}">Siswa menunggak</div><div style="{{ $val }}">{{ number_format($s['students_owing'], 0, ',', '.') }}</div></div>
                    </div>
                @endif

                @if ($row['closed'])
                    <p style="margin: 1rem 0 0; font-size: .85rem; {{ $muted }}">
                        Ditutup {{ $row['closing']->closed_at->translatedFormat('d M Y H:i') }} oleh {{ $row['closing']->closer?->name ?? '—' }}.
                        @if ($row['closing']->note) Catatan: {{ $row['closing']->note }} @endif
                        @if ($row['closing']->snapshot)
                            Saat ditutup, sisa tunggakan Rp {{ number_format($row['closing']->snapshot['outstanding'] ?? 0, 0, ',', '.') }}.
                        @endif
                    </p>
                    <div style="margin-top: .75rem">
                        <x-filament::button color="gray" size="sm" icon="heroicon-o-lock-open" wire:click="mountAction('reopen', { year: {{ $y->id }} })">Buka kembali</x-filament::button>
                    </div>
                @elseif ($y->is_active)
                    <p style="margin: 1rem 0 0; font-size: .85rem; {{ $muted }}">Tahun berjalan. Bisa ditutup setelah tahun ajaran berikutnya aktif.</p>
                @elseif ($empty)
                    <p style="margin: 0; font-size: .85rem; {{ $muted }}">Tidak ada tagihan di tahun ini, tidak perlu ditutup.</p>
                @else
                    @if ($due)
                        <p style="margin: 1rem 0 0; font-size: .85rem; color: var(--warning-600)">Tahun ini sudah berakhir dan tahun {{ $nextActive->name }} sudah aktif — sudah waktunya ditutup.</p>
                    @endif
                    @foreach ($row['check']['blocking'] as $msg)
                        <p style="margin: .5rem 0 0; font-size: .85rem; color: var(--danger-600)">✕ {{ $msg }}</p>
                    @endforeach
                    @foreach ($row['check']['info'] as $msg)
                        <p style="margin: .5rem 0 0; font-size: .85rem; {{ $muted }}">ℹ {{ $msg }}</p>
                    @endforeach

                    <div style="margin-top: .75rem">
                        @if (! $isNext)
                            <span style="font-size: .85rem; {{ $muted }}">Tutup tahun yang lebih lama dulu.</span>
                        @elseif ($row['check']['blocking'] === [])
                            <x-filament::button color="danger" size="sm" icon="heroicon-o-lock-closed" wire:click="mountAction('close', { year: {{ $y->id }} })">Tutup buku</x-filament::button>
                        @endif
                    </div>
                @endif
            </x-filament::section>
        @endforeach
    </div>
    <x-filament-actions::modals />
</x-filament-panels::page>
