<div style="font-size: 0.875rem; display: grid; gap: 1rem;">
    <table style="width: 100%; border-collapse: collapse;">
        @foreach ($meta as $label => $value)
            <tr>
                <td style="padding: 0.25rem 0.75rem 0.25rem 0; opacity: 0.7; white-space: nowrap; vertical-align: top;">
                    {{ $label }}</td>
                <td style="padding: 0.25rem 0;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    @forelse ($sections as $section)
        <div>
            <div style="font-weight: 600; margin-bottom: 0.25rem;">{{ $section['label'] }}</div>
            <ul style="margin: 0; padding-left: 1.25rem; list-style: disc;">
                @foreach ($section['items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @empty
        <div style="opacity: 0.7;">Tidak ada rincian tambahan.</div>
    @endforelse
</div>
