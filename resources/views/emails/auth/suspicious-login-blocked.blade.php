<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suspicious Sign-in Attempt Blocked</title>
</head>
<body style="margin:0;background:#f5f5f5;font-family:Arial,sans-serif;color:#171717;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f5f5;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e5e5;">
                <tr>
                    <td style="padding:26px 32px;background:#991b1b;color:#ffffff;">
                        <div style="font-size:20px;font-weight:700;">HIMS</div>
                        <div style="margin-top:3px;color:#fecaca;font-size:11px;letter-spacing:1.2px;text-transform:uppercase;">Security Alert</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <h1 style="margin:0;font-size:24px;line-height:32px;">Suspicious sign-in blocked</h1>
                        <p style="margin:16px 0 0;color:#525252;font-size:15px;line-height:24px;">A sign-in request to your HIMS account was rejected by the account owner.</p>

                        <div style="margin:20px 0;padding:16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;font-size:14px;line-height:22px;">
                            <div><strong>Device:</strong> {{ $deviceSummary }}</div>
                            @if ($ipAddress)
                                <div style="margin-top:4px;"><strong>IP Address:</strong> {{ $ipAddress }}</div>
                            @endif
                            <div style="margin-top:4px;"><strong>Action taken:</strong> Access denied &amp; device temporarily blocked for {{ $cooldownMinutes }} minutes.</div>
                        </div>

                        <p style="margin:16px 0 0;color:#525252;font-size:14px;line-height:22px;">Your current active session remains secure. However, because valid credentials were submitted from an unrecognized device, someone may know your password.</p>

                        <div style="margin-top:24px;padding-top:18px;border-top:1px solid #e5e5e5;color:#737373;font-size:13px;line-height:20px;">
                            <strong>Recommended Action:</strong> Change your HIMS password immediately to prevent unauthorized access.
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
