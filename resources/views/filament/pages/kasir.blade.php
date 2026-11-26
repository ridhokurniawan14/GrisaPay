@php
    $rp = fn(int $n): string => 'Rp ' . number_format($n, 0, ',', '.');
    $student = $this->student;
    $summary = $this->summary;
    $methods = \App\Models\Payment::METHODS;
    $canPrint = auth()->user()?->can(\App\Enums\PermissionName::PaymentsPrint->value);
@endphp

<x-filament-panels::page>
    <style>
        .ks {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1.25rem;
            font-size: .875rem;
        }

        @media (min-width: 1024px) {
            .ks {
                grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
                align-items: start;
            }
        }

        .ks-card {
            border: 1px solid var(--gray-200);
            border-radius: .875rem;
            background: #fff;
            padding: 1rem;
        }

        .dark .ks-card {
            border-color: var(--gray-700);
            background: var(--gray-900);
        }

        .ks-h {
            font-weight: 600;
            font-size: .9375rem;
            margin-bottom: .625rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }

        .ks-muted {
            color: var(--gray-500);
        }

        .ks-input {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: .5rem;
            padding: .5rem .75rem;
            background: transparent;
            color: inherit;
            font: inherit;
        }

        .dark .ks-input {
            border-color: var(--gray-600);
        }

        select.ks-input {
            color-scheme: light;
        }

        .dark select.ks-input {
            color-scheme: dark;
            background-color: var(--gray-900);
            color: var(--gray-100);
        }

        .dark select.ks-input optgroup {
            background-color: var(--gray-900);
            color: var(--gray-100);
        }

        .dark .ks-class {
            color: var(--gray-50) !important;
        }

        .dark select.ks-input option {
            background-color: var(--gray-900);
            color: var(--gray-100);
        }

        .ks-money {
            position: relative;
        }

        .ks-money>span {
            position: absolute;
            left: .875rem;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 600;
            color: var(--gray-500);
            pointer-events: none;
        }

        .ks-money>input {
            padding-left: 2.75rem;
        }

        .ks-input:focus {
            outline: 2px solid var(--primary-500);
            outline-offset: 0;
            border-color: var(--primary-500);
        }

        .ks-input-lg {
            font-size: 1.5rem;
            font-weight: 700;
            padding: .625rem .875rem;
            font-variant-numeric: tabular-nums;
        }

        .ks-input-err {
            border-color: var(--danger-500) !important;
            outline-color: var(--danger-500) !important;
        }

        .ks-label {
            display: block;
            font-size: .75rem;
            font-weight: 500;
            color: var(--gray-600);
            margin: .75rem 0 .25rem;
        }

        .dark .ks-label {
            color: var(--gray-400);
        }

        .ks-err {
            color: var(--danger-600);
            font-size: .8125rem;
            margin-top: .375rem;
        }

        .dark .ks-err {
            color: var(--danger-400);
        }

        .ks-list button {
            display: flex;
            width: 100%;
            justify-content: space-between;
            align-items: center;
            gap: .75rem;
            text-align: left;
            padding: .5rem .625rem;
            border-radius: .5rem;
            border: 0;
            background: transparent;
            color: inherit;
            cursor: pointer;
            font: inherit;
        }

        .ks-list button:hover {
            background: var(--gray-100);
        }

        .dark .ks-list button:hover {
            background: var(--gray-800);
        }

        .ks-chip {
            padding: .0625rem .5rem;
            border-radius: 9999px;
            font-size: .6875rem;
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .dark .ks-chip {
            background: var(--gray-800);
            color: var(--gray-300);
        }

        .ks-chip-warn {
            background: var(--warning-100);
            color: var(--warning-700);
        }

        .dark .ks-chip-warn {
            background: var(--warning-900);
            color: var(--warning-300);
        }

        .ks-chip-ok {
            background: var(--success-100);
            color: var(--success-700);
        }

        .dark .ks-chip-ok {
            background: var(--success-900);
            color: var(--success-300);
        }

        .ks-chip-bad {
            background: var(--danger-100);
            color: var(--danger-700);
        }

        .dark .ks-chip-bad {
            background: var(--danger-900);
            color: var(--danger-300);
        }

        .ks-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));
            gap: .5rem;
            margin-bottom: .75rem;
        }

        .ks-stat {
            border: 1px solid var(--gray-200);
            border-radius: .625rem;
            padding: .5rem .75rem;
        }

        .dark .ks-stat {
            border-color: var(--gray-700);
        }

        .ks-stat small {
            display: block;
            font-size: .6875rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--gray-500);
        }

        .ks-stat b {
            font-size: 1rem;
            font-variant-numeric: tabular-nums;
        }

        .ks-danger {
            color: var(--danger-600);
        }

        .dark .ks-danger {
            color: var(--danger-400);
        }

        .ks-ok {
            color: var(--success-600);
        }

        .dark .ks-ok {
            color: var(--success-400);
        }

        .ks-table {
            width: 100%;
            border-collapse: collapse;
            font-variant-numeric: tabular-nums;
        }

        .ks-table th {
            text-align: right;
            font-size: .6875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--gray-500);
            padding: .25rem .5rem;
        }

        .ks-table td {
            text-align: right;
            padding: .375rem .5rem;
            border-top: 1px solid var(--gray-100);
        }

        .dark .ks-table td {
            border-top-color: var(--gray-800);
        }

        .ks-table th:first-child,
        .ks-table td:first-child {
            text-align: left;
        }

        .ks-seg {
            display: flex;
            gap: .375rem;
            flex-wrap: wrap;
        }

        .ks-seg button {
            flex: 1 1 0;
            padding: .5rem .75rem;
            border-radius: .5rem;
            border: 1px solid var(--gray-300);
            background: transparent;
            color: inherit;
            cursor: pointer;
            font: inherit;
            font-weight: 500;
        }

        .dark .ks-seg button {
            border-color: var(--gray-600);
        }

        .ks-seg button.on {
            background: var(--primary-600);
            border-color: var(--primary-600);
            color: #fff;
        }

        .ks-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .375rem;
            padding: .625rem 1rem;
            border-radius: .5rem;
            border: 1px solid var(--gray-300);
            background: transparent;
            color: inherit;
            cursor: pointer;
            font: inherit;
            font-weight: 500;
        }

        .dark .ks-btn {
            border-color: var(--gray-600);
        }

        .ks-btn-sm {
            padding: .25rem .625rem;
            font-size: .75rem;
        }

        .ks-btn-primary {
            background: var(--primary-600);
            border-color: var(--primary-600);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            padding: .875rem 1rem;
        }

        .ks-btn-primary:hover:not(:disabled) {
            background: var(--primary-500);
        }

        .ks-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .ks-preview {
            background: var(--gray-50);
            border-radius: .625rem;
            padding: .625rem .75rem;
            margin-top: .75rem;
        }

        .dark .ks-preview {
            background: var(--gray-800);
        }

        .ks-row {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            padding: .125rem 0;
            font-variant-numeric: tabular-nums;
        }

        .ks-success {
            border-color: var(--success-300);
            background: var(--success-50);
        }

        .dark .ks-success {
            border-color: var(--success-700);
            background: var(--success-950);
        }

        .ks-check {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-top: .625rem;
            cursor: pointer;
        }

        .ks-grid2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
            gap: .75rem;
        }
    </style>

    @php
        $shift = $this->shift;
        $needShift = $this->shiftRequired && !$shift;
        $stats = $this->shiftStats;
    @endphp

    @if ($needShift)
        <div class="ks-card" style="max-width:32rem; margin:2rem auto;">
            <div class="ks-h">Buka shift kas</div>
            <div class="ks-muted" style="margin-bottom:.75rem;">Shift harus dibuka sebelum mencatat pembayaran. Isi modal
                awal (uang tunai yang ada di laci saat mulai), kosongkan atau isi 0 bila tidak ada.</div>
            @can(\App\Enums\PermissionName::CashSessionsManage->value)
                <label class="ks-label">Modal awal</label>
                <div class="ks-money" style="margin-bottom:.75rem;">
                    <span>Rp</span>
                    <input type="text" class="ks-input" inputmode="numeric" placeholder="0" autocomplete="off" x-data
                        x-mask:dynamic="$money($input, ',', '.', 0)" wire:model="openingCash"
                        wire:keydown.enter="openShift">
                </div>
                <button type="button" class="ks-btn ks-btn-primary" wire:click="openShift" wire:loading.attr="disabled"
                    wire:target="openShift">
                    <span wire:loading.remove wire:target="openShift">Buka shift</span>
                    <span wire:loading wire:target="openShift">Membuka…</span>
                </button>
            @else
                <div class="ks-muted">Anda tidak punya izin membuka shift kas. Hubungi bendahara/admin.</div>
            @endcan
        </div>
    @else
        <div class="ks" wire:poll.10s.visible>
            {{-- ========== KIRI: cari siswa + riwayat ========== --}}
            <div style="display:grid; gap:1.25rem;">
                <div class="ks-card">
                    <div class="ks-h">Cari siswa</div>
                    <input type="text" class="ks-input" placeholder="Ketik nama, NISN, atau NIS (min. 2 huruf)…"
                        wire:model.live.debounce.300ms="search" autofocus autocomplete="off">

                    @if (mb_strlen(trim($search)) >= 2)
                        <div class="ks-list" style="margin-top:.5rem;">
                            @forelse ($this->results as $r)
                                <button type="button" wire:click="selectStudent({{ $r['id'] }})">
                                    <span>
                                        <b>{{ $r['name'] }}</b><br>
                                        <span class="ks-muted">NISN {{ $r['nisn'] ?? '-' }} ·
                                            {{ $r['classroom'] }}</span>
                                    </span>
                                    <span
                                        class="ks-chip {{ $r['status'] === 'aktif' ? '' : 'ks-chip-warn' }}">{{ ucfirst($r['status']) }}</span>
                                </button>
                            @empty
                                <div class="ks-muted" style="padding:.5rem;">Tidak ada siswa bertagihan yang cocok.
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>

                @if ($student)
                    <div class="ks-card">
                        <div class="ks-h">Riwayat pembayaran</div>
                        @if ($this->history->isEmpty())
                            <div class="ks-muted">Belum ada pembayaran.</div>
                        @else
                            <table class="ks-table">
                                <thead>
                                    <tr>
                                        <th>Kwitansi</th>
                                        <th>Jumlah</th>
                                        <th>Cetak</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->history as $h)
                                        <tr>
                                            <td>
                                                <b>{{ $h->receipt_no ?? '(menunggu)' }}</b><br>
                                                <span class="ks-muted"
                                                    style="font-size:.75rem;">{{ $h->paid_at->format('d/m/Y H:i') }} WIB
                                                    · {{ $h->methodText() }}</span>
                                            </td>
                                            <td>
                                                {{ $rp($h->amount) }}<br>
                                                <span
                                                    class="ks-chip {{ match ($h->status) {'sah' => 'ks-chip-ok','menunggu' => 'ks-chip-warn','ditolak' => 'ks-chip-bad',default => ''} }}">{{ $h->status === 'sah' ? 'Sah' : ucfirst($h->status) }}</span>
                                            </td>
                                            <td>{{ $h->prints->count() }}×</td>
                                            <td>
                                                @if ($h->status === 'sah' && $canPrint)
                                                    <button type="button" class="ks-btn ks-btn-sm"
                                                        wire:click="printReceipt({{ $h->id }})"
                                                        wire:loading.attr="disabled" wire:target="printReceipt">
                                                        {{ $h->prints->count() === 0 ? 'Cetak' : 'Cetak ulang' }}
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endif
            </div>

            {{-- ========== KANAN: tagihan + form bayar ========== --}}
            <div style="display:grid; gap:1.25rem;">
                @if (!$student)
                    <div class="ks-card" style="text-align:center; padding:3rem 1rem;">
                        <div style="font-size:1.125rem; font-weight:600;">Pilih siswa untuk mulai</div>
                        <div class="ks-muted" style="margin-top:.25rem;">Cari berdasarkan nama atau NISN di kolom kiri.
                            Alumni yang masih punya tunggakan juga bisa dicari.</div>
                    </div>
                @else
                    @if ($this->lastPayment)
                        @php $lp = $this->lastPayment; @endphp
                        <div class="ks-card ks-success">
                            <div class="ks-h" style="margin-bottom:.25rem;">
                                <span>{{ $lp->status === 'sah' ? 'Pembayaran tersimpan' : 'Pembayaran tersimpan · menunggu verifikasi' }}</span>
                                @if ($lp->status === 'sah' && $canPrint)
                                    <button type="button" class="ks-btn ks-btn-sm"
                                        wire:click="printReceipt({{ $lp->id }})" wire:loading.attr="disabled"
                                        wire:target="printReceipt">
                                        {{ $lp->prints->count() === 0 ? 'Cetak struk' : 'Cetak ulang' }}
                                    </button>
                                @endif
                            </div>
                            <div>{{ $lp->receipt_no ?? 'Nomor kwitansi diberikan setelah diverifikasi' }} ·
                                {{ $rp($lp->amount) }} · {{ $lp->methodText() }}</div>
                            <div style="margin-top:.5rem;">
                                <button type="button" class="ks-btn ks-btn-sm" wire:click="clearStudent">Siswa
                                    berikutnya</button>
                            </div>
                        </div>
                    @endif

                    <div class="ks-card">
                        <div class="ks-h">
                            <span>{{ $student->name }}</span>
                            <button type="button" class="ks-btn ks-btn-sm" wire:click="clearStudent">Ganti
                                siswa</button>
                        </div>
                        <div class="ks-muted" style="margin-bottom:.75rem;">NISN {{ $student->nisn ?? '-' }} ·
                            {{ $student->status === 'aktif' ? 'Kelas' : 'Kelas terakhir' }} <b
                                style="color: var(--gray-950);" class="ks-class">{{ $this->classroom }}</b> ·
                            {{ ucfirst($student->status) }}</div>

                        @if ($summary)
                            <div class="ks-stats">
                                <div class="ks-stat"><small>Total
                                        tagihan</small><b>{{ $rp($summary['total']['bill']) }}</b></div>
                                <div class="ks-stat">
                                    <small>Diskon</small><b>{{ $rp($summary['total']['discount']) }}</b></div>
                                <div class="ks-stat"><small>Dibayar</small><b
                                        class="ks-ok">{{ $rp($summary['total']['paid']) }}</b></div>
                                <div class="ks-stat"><small>Sisa</small><b
                                        class="{{ $summary['total']['remaining'] > 0 ? 'ks-danger' : 'ks-ok' }}">{{ $rp($summary['total']['remaining']) }}</b>
                                </div>
                            </div>

                            <table class="ks-table">
                                <thead>
                                    <tr>
                                        <th>Kategori</th>
                                        <th>Tagihan</th>
                                        <th>Dibayar</th>
                                        <th>Sisa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($summary['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ $rp($row['bill'] - $row['discount']) }}</td>
                                            <td>{{ $rp($row['paid']) }}</td>
                                            <td style="font-weight:600;"
                                                class="{{ $row['remaining'] > 0 ? '' : 'ks-ok' }}">
                                                {{ $row['remaining'] > 0 ? $rp($row['remaining']) : 'Lunas' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                    <div class="ks-card">
                        <div class="ks-h">Catat pembayaran</div>

                        <label class="ks-label" style="margin-top:0;">Metode</label>
                        <div class="ks-seg">
                            @foreach ($methods as $key => $label)
                                <button type="button" class="{{ $method === $key ? 'on' : '' }}"
                                    wire:click="$set('method', '{{ $key }}')">{{ $label }}</button>
                            @endforeach
                        </div>
                        @if ($method !== 'cash')
                            <div class="ks-muted" style="margin-top:.375rem; font-size:.8125rem;">Transfer/QRIS menunggu
                                verifikasi bendahara. Struk dicetak setelah diverifikasi.</div>
                        @endif

                        <label class="ks-label">Dibayarkan untuk</label>
                        <select class="ks-input" wire:model.live="focus">
                            <option value="">Otomatis (tunggakan dulu, lalu urutan tertua)</option>
                            @foreach ($this->focusOptions as $group)
                                <optgroup label="{{ $group['label'] }}">
                                    <option value="{{ $group['all']['value'] }}">{{ $group['all']['label'] }}
                                    </option>
                                    @foreach ($group['items'] as $item)
                                        <option value="{{ $item['value'] }}">&bull; {{ $item['label'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @if (str_starts_with($focus, 'item:'))
                            <div class="ks-muted" style="margin-top:.25rem; font-size:.8125rem;">Pembayaran hanya
                                untuk rincian yang dipilih.</div>
                        @elseif ($focus === '')
                            <div class="ks-muted" style="margin-top:.25rem; font-size:.8125rem;">Tanpa pilihan, uang
                                dibagi ke tagihan tertua lebih dulu (urutan kategori dan rincian).</div>
                        @endif

                        <label class="ks-label">Jumlah dibayar <span class="ks-muted">(maks.
                                {{ $rp($this->max) }})</span>
                        </label>
                        <div class="ks-money">
                            <span>Rp</span>
                            <input type="text" inputmode="numeric" autocomplete="off" placeholder="0"
                                class="ks-input ks-input-lg {{ $this->amountError ? 'ks-input-err' : '' }}" x-data
                                x-mask:dynamic="$money($input, ',', '.', 0)" wire:model.live.debounce.250ms="amount">
                        </div>
                        @if ($this->amountError)
                            <div class="ks-err">{{ $this->amountError }}</div>
                        @endif
                        <div style="display:flex; gap:.375rem; margin-top:.5rem; flex-wrap:wrap;">
                            <button type="button" class="ks-btn ks-btn-sm" wire:click="fillAll"
                                @disabled($this->max === 0)>Lunasi seluruhnya ({{ $rp($this->max) }})</button>
                        </div>

                        @if ($method === 'cash')
                            <div class="ks-grid2">
                                <div>
                                    <label class="ks-label">Uang diterima <span class="ks-muted">(opsional, untuk
                                            kembalian)</span></label>
                                    <div class="ks-money">
                                        <span>Rp</span>
                                        <input type="text" inputmode="numeric" autocomplete="off"
                                            class="ks-input" placeholder="0" x-data
                                            x-mask:dynamic="$money($input, ',', '.', 0)"
                                            wire:model.live.debounce.250ms="tendered">
                                    </div>
                                </div>
                                <div>
                                    <label class="ks-label">Kembalian</label>
                                    <div class="ks-input"
                                        style="font-weight:700; {{ ($this->change ?? 0) < 0 ? 'color:var(--danger-600);' : '' }}">
                                        @if ($this->change === null)
                                            -
                                        @elseif ($this->change < 0)
                                            Kurang {{ $rp(abs($this->change)) }}
                                        @else
                                            {{ $rp($this->change) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <label class="ks-label">No. referensi {{ $method === 'qris' ? 'QRIS' : 'transfer' }} <span
                                    class="ks-muted">(opsional)</span></label>
                            <input type="text" class="ks-input" wire:model="reference" maxlength="100"
                                autocomplete="off">
                        @endif

                        <label class="ks-check">
                            <input type="checkbox" wire:model.live="samePayer">
                            <span>Penyetor sama dengan siswa <span
                                    class="ks-muted">({{ $student->name }})</span></span>
                        </label>
                        @unless ($samePayer)
                            <label class="ks-label">Nama penyetor</label>
                            <input type="text" class="ks-input" wire:model.live.debounce.300ms="payerName"
                                maxlength="100" placeholder="Mis. Siti Aminah (Ibu)" autocomplete="off">
                        @endunless

                        <label class="ks-label">Catatan <span class="ks-muted">(opsional)</span></label>
                        <input type="text" class="ks-input" wire:model="note" maxlength="500"
                            autocomplete="off">

                        @if ($this->duplicateWarning)
                            @php $dw = $this->duplicateWarning; @endphp
                            <div class="ks-preview"
                                style="border:1px solid var(--warning-500); background: var(--warning-50);">
                                <b>Hati-hati, mungkin terinput dua kali.</b>
                                <div style="margin:.25rem 0;">Pembayaran {{ $rp($dw->amount) }}
                                    ({{ $dw->methodText() }}) untuk siswa ini sudah dicatat pukul
                                    {{ $dw->paid_at->format('H:i') }} WIB
                                    @if ($dw->receipt_no)
                                        · {{ $dw->receipt_no }}
                                    @endif.
                                </div>
                                <label class="ks-check" style="margin-top:.25rem;">
                                    <input type="checkbox" wire:model.live="confirmDuplicate">
                                    <span>Ya, ini pembayaran terpisah (bukan input ulang)</span>
                                </label>
                            </div>
                        @endif

                        @if ($this->preview && $this->amountError === null)
                            <div class="ks-preview">
                                <div class="ks-muted" style="font-size:.75rem; margin-bottom:.25rem;">UANG INI AKAN
                                    DIBAGI KE</div>
                                @foreach ($this->preview['rows'] as $p)
                                    <div class="ks-row">
                                        <span>{{ $p['label'] }}</span><b>{{ $rp($p['amount']) }}</b></div>
                                @endforeach
                            </div>
                        @endif

                        <div style="margin-top:1rem;">
                            <button type="button" class="ks-btn ks-btn-primary" wire:click="submit"
                                wire:loading.attr="disabled" wire:target="submit" @disabled(!$this->canSubmit)>
                                <span wire:loading.remove wire:target="submit">
                                    {{ $method === 'cash' ? 'Simpan & cetak struk' : 'Simpan (menunggu verifikasi)' }}
                                    @if ($this->amountInt > 0 && !$this->amountError)
                                        · {{ $rp($this->amountInt) }}
                                    @endif
                                </span>
                                <span wire:loading wire:target="submit">Menyimpan…</span>
                            </button>
                            @if (!$this->canSubmit && $student && $this->amountInt === 0)
                                <div class="ks-muted"
                                    style="text-align:center; margin-top:.375rem; font-size:.8125rem;">Isi jumlah
                                    pembayaran untuk melanjutkan.</div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <script>
        // Cetak lewat iframe tersembunyi: tidak diblokir pop-up dan halaman kasir tetap terbuka.
        window.addEventListener('print-receipt', function(e) {
            var d = e.detail;
            var url = (d && d.url) || (Array.isArray(d) && (d[0].url || d[0])) || null;
            if (!url) {
                return;
            }
            var old = document.getElementById('receipt-frame');
            if (old) {
                old.remove();
            }
            var f = document.createElement('iframe');
            f.id = 'receipt-frame';
            f.style.cssText = 'position:fixed;right:0;bottom:0;width:1px;height:1px;border:0;opacity:0;';
            f.src = url;
            document.body.appendChild(f);
        });
    </script>
</x-filament-panels::page>
