@php
    $primaryColor = app(\App\Services\BrandingSettings::class)->primaryColorHex();
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0;">
    <tr>
        <td align="center" style="padding: 20px 24px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
            <span style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 32px; font-weight: 700; letter-spacing: 0.25em; color: {{ $primaryColor }};">{{ $code }}</span>
        </td>
    </tr>
</table>
