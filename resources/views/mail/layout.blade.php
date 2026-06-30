@php
    $branding = app(\App\Services\BrandingSettings::class);
    $primaryColor = $branding->primaryColorHex();
    $footerText = $branding->get(\App\Services\BrandingSettings::KEY_FOOTER_TEXT);
    $tosUrl = $branding->get(\App\Services\BrandingSettings::KEY_TOS_URL);
    $privacyUrl = $branding->get(\App\Services\BrandingSettings::KEY_PRIVACY_URL);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $branding->appName() }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #374151;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; border: 1px solid #e5e7eb; overflow: hidden;">
                    <tr>
                        <td style="padding: 24px 32px; border-bottom: 1px solid #e5e7eb;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-right: 12px; vertical-align: middle;">
                                        <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->appName() }}" width="32" height="32" style="display: block; max-width: 140px; max-height: 32px; width: auto; height: auto;">
                                    </td>
                                    <td style="vertical-align: middle;">
                                        <span style="font-size: 16px; font-weight: 600; color: #111827;">{{ $branding->appName() }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            @yield('content')
                        </td>
                    </tr>
                    @if ($footerText || $tosUrl || $privacyUrl)
                        <tr>
                            <td style="padding: 20px 32px; border-top: 1px solid #e5e7eb; background-color: #f9fafb;">
                                <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #6b7280; text-align: center;">
                                    @if ($footerText)
                                        <span>{{ $footerText }}</span>
                                    @endif
                                    @if ($footerText && ($tosUrl || $privacyUrl))
                                        <span style="color: #d1d5db;"> &middot; </span>
                                    @endif
                                    @if ($tosUrl)
                                        <a href="{{ $tosUrl }}" style="color: {{ $primaryColor }}; text-decoration: none;">@lang('approval.mail.terms')</a>
                                    @endif
                                    @if ($tosUrl && $privacyUrl)
                                        <span style="color: #d1d5db;"> &middot; </span>
                                    @endif
                                    @if ($privacyUrl)
                                        <a href="{{ $privacyUrl }}" style="color: {{ $primaryColor }}; text-decoration: none;">@lang('approval.mail.privacy')</a>
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
