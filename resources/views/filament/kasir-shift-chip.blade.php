@php
    $user = auth()->user();
    $shift = $user ? app(\App\Services\CashShiftService::class)->current($user) : null;
@endphp
@if ($shift)
    @php
        $cash = $shift->cashIn();
        $rp = fn (int $n): string => 'Rp '.number_format($n, 0, ',', '.');
        $old = ! $shift->opened_at->isToday();
    @endphp
    <div class="hidden md:flex" style="align-items:center; gap:.5rem; font-size:.75rem; line-height:1.25; color: var(--gray-600); border:1px solid var(--gray-200); border-radius:.5rem; padding:.3rem .65rem; margin-inline-end:.5rem;"
         title="Modal {{ $rp((int) $shift->opening_cash) }} · tunai masuk {{ $rp($cash) }}">
        <span style="width:.5rem; height:.5rem; border-radius:9999px; background: {{ $old ? 'var(--warning-500)' : 'var(--success-500)' }};"></span>
        <span>
            <b>Shift</b> {{ $shift->opened_at->format($old ? 'd/m H:i' : 'H:i') }}
            · tunai {{ $rp($cash) }} · {{ $shift->payments()->count() }} trx
            @if ($old)<b style="color: var(--warning-600);"> · belum ditutup</b>@endif
        </span>
    </div>
@endif
