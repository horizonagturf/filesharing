@php
    $primaryColor = app(\App\Services\BrandingSettings::class)->primaryColorHex();
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
    <tr>
        <td align="center" style="border-radius: 6px; background-color: {{ $primaryColor }};">
            <a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; padding: 12px 24px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">{{ $label }}</a>
        </td>
    </tr>
</table>
