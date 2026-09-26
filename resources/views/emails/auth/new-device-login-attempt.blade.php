<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Sign-in Attempt</title>
</head>
<body style="margin:0;background:#f5f5f5;font-family:Arial,sans-serif;color:#171717;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f5f5;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e5e5;">
                <tr>
                    <td style="padding:26px 32px;background:#0a0a0a;color:#ffffff;">
                        <div style="font-size:20px;font-weight:700;">HIMS</div>
                        <div style="margin-top:3px;color:#a3a3a3;font-size:11px;letter-spacing:1.2px;text-transform:uppercase;">Security Alert</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <h1 style="margin:0;font-size:24px;line-height:32px;">New sign-in attempt</h1>
                        <p style="margin:16px 0 0;color:#525252;font-size:15px;line-height:24px;">An unrecognized device is attempting to sign in to your HIMS account.</p>

                        <div style="margin:20px 0;padding:16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;line-height:22px;">
                            <div><strong>Device:</strong> {{ $deviceSummary }}</div>
                            @if ($ipAddress)
                                <div style="margin-top:4px;"><strong>IP Address:</strong> {{ $ipAddress }}</div>
                            @endif
                            <div style="margin-top:4px;"><strong>Time:</strong> {{ $requestedAt }}</div>
                        </div>

                        @if ($otp)
                            <p style="margin:16px 0 0;color:#525252;font-size:15px;line-height:24px;">Enter the following verification code to authorize this device and complete your sign in:</p>
                            <div style="margin:24px 0;padding:18px;text-align:center;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;color:#174cb6;font-size:32px;font-weight:700;letter-spacing:8px;">{{ $otp }}</div>
                            <p style="margin:0;color:#525252;font-size:14px;line-height:22px;">This code expires in {{ $expiresInMinutes }} minutes and can only be used once.</p>
                        @else
                            <p style="margin:16px 0 0;color:#525252;font-size:14px;line-height:22px;">If your account is currently signed in on another device, an approval prompt has been sent there.</p>
                        @endif

                        <div style="margin-top:24px;padding-top:18px;border-top:1px solid #e5e5e5;color:#737373;font-size:13px;line-height:20px;">
                            <strong>Did not request this?</strong> If you did not initiate this sign-in attempt, someone may have your password. We recommend changing your password immediately from your account security settings.
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
