<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ __('mail.'.$kind.'.subject', [], $locale) }}</title>
</head>
<body style="margin:0;padding:0;background-color:#fbf3e4;color:#4c2b18;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ __('mail.'.$kind.'.title', [], $locale) }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fbf3e4" style="width:100%;background-color:#fbf3e4;">
    <tr><td align="center" style="padding:32px 16px;">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
            <tr><td style="padding:8px 8px 26px;">
                <a href="{{ url('/'.$locale) }}" style="color:#4c2b18;text-decoration:none;font-family:Georgia,'Times New Roman',serif;font-size:27px;font-weight:bold;line-height:1.3;">lessons.atapin.de</a>
                <p style="margin:6px 0 0;color:#786b52;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;">{{ __('mail.tagline', [], $locale) }}</p>
            </td></tr>
            <tr><td bgcolor="#fffaf0" style="padding:30px 24px;border:1px solid #e4d4b8;border-radius:14px;background-color:#fffaf0;">
                <h1 style="margin:0 0 24px;color:#4c2b18;font-family:Georgia,'Times New Roman',serif;font-size:28px;font-weight:normal;line-height:1.25;">{{ __('mail.'.$kind.'.title', [], $locale) }}</h1>
                <p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.65;">{{ __('mail.greeting', ['name' => $recipientName], $locale) }}</p>
                <p style="margin:0 0 24px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.65;">{{ __('mail.'.$kind.'.intro', [], $locale) }}</p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;max-width:100%;">
                    <tr><td align="center" bgcolor="#a54b1b" style="border-radius:8px;background-color:#a54b1b;">
                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 20px;border:1px solid #a54b1b;border-radius:8px;color:#fffaf0;text-decoration:none;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;line-height:1.4;">{{ __('mail.'.$kind.'.action', [], $locale) }}</a>
                    </td></tr>
                </table>
                <p style="margin:0 0 16px;color:#786b52;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;">{{ __('mail.'.$kind.'.expiry', ['minutes' => $expiresMinutes], $locale) }}</p>
                <p style="margin:0;color:#786b52;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;">{{ __('mail.'.$kind.'.ignore', [], $locale) }}</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:24px;table-layout:fixed;">
                    <tr><td style="padding-top:20px;border-top:1px solid #e4d4b8;">
                        <p style="margin:0 0 8px;color:#786b52;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;">{{ __('mail.fallback', [], $locale) }}</p>
                        <a href="{{ $actionUrl }}" style="color:#a54b1b;text-decoration:underline;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.7;overflow-wrap:anywhere;word-break:break-all;">{{ $actionUrl }}</a>
                    </td></tr>
                </table>
            </td></tr>
            <tr><td style="padding:22px 8px 8px;color:#786b52;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;">{{ __('mail.footer', [], $locale) }}</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
