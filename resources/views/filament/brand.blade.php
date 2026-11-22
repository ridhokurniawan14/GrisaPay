@php
    try {
        $brandLogoPath = \App\Models\InstitutionSetting::current()->logo_path;
    } catch (\Throwable) {
        $brandLogoPath = null;
    }

    $brandLogoUrl = $brandLogoPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($brandLogoPath) : null;
@endphp
<div style="display: flex; align-items: center; gap: 0.6rem;">
    @if ($brandLogoUrl)
        <img src="{{ $brandLogoUrl }}" alt="" style="height: 2.25rem; width: auto;">
    @endif
    <span style="font-weight: 700; font-size: 1.25rem; line-height: 1;">GrisaPay</span>
</div>
