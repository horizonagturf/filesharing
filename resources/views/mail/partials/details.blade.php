<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    @foreach ($rows as $label => $value)
        <tr>
            <td style="padding: 10px 16px; background-color: #f9fafb; font-size: 13px; font-weight: 600; color: #6b7280; width: 35%; border-bottom: 1px solid #e5e7eb; vertical-align: top;">{{ $label }}</td>
            <td style="padding: 10px 16px; font-size: 14px; color: #111827; border-bottom: 1px solid #e5e7eb; vertical-align: top;">{{ $value }}</td>
        </tr>
    @endforeach
</table>
