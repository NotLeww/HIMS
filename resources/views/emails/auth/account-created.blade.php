<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your HIMS account is ready</title>
</head>
<body style="margin:0;background:#f4f7fb;font-family:'Segoe UI',Tahoma,sans-serif;color:#172033;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:36px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #dbe4ef;">
                <tr>
                    <td style="padding:26px 32px;background:#174c86;color:#ffffff;">
                        <div style="font-size:21px;font-weight:700;letter-spacing:-0.3px;">HIMS</div>
                        <div style="margin-top:4px;color:#dbeafe;font-size:11px;letter-spacing:1.1px;text-transform:uppercase;">Account Services</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <div style="display:inline-block;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#174c86;font-size:12px;font-weight:700;">Ready for activation</div>
                        <h1 style="margin:18px 0 0;font-size:26px;line-height:34px;letter-spacing:-0.5px;color:#172033;">Welcome to HIMS, {{ $name }}</h1>
                        <p style="margin:14px 0 0;color:#4b5565;font-size:15px;line-height:24px;">Your account has been created. Activate it to verify your contact details and create your secure password.</p>

                        <table role="presentation" cellspacing="0" cellpadding="0" style="margin:26px auto;">
                            <tr>
                                <td align="center" style="border-radius:8px;background:#174c86;">
                                    <a href="{{ $activationUrl }}" style="display:inline-block;padding:13px 24px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">Activate HIMS Account</a>
                                </td>
                            </tr>
                        </table>

                        <div style="padding:18px;background:#f8fafc;border:1px solid #dbe4ef;border-radius:10px;">
                            <div style="font-size:14px;font-weight:700;color:#172033;">What happens next</div>
                            <div style="margin-top:10px;color:#4b5565;font-size:13px;line-height:21px;">Verify your registered contact details, create your password, then sign in using your new HIMS credentials.</div>
                        </div>

                        <p style="margin:22px 0 0;color:#647083;font-size:13px;line-height:21px;">If you did not expect this account, please contact your HIMS administrator.</p>

                        <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e5eaf0;color:#6b7280;font-size:12px;line-height:19px;">
                            Button not working? Copy and paste this address into your browser:<br>
                            <a href="{{ $activationUrl }}" style="color:#174c86;text-decoration:underline;word-break:break-all;">{{ $activationUrl }}</a>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px;background:#f8fafc;border-top:1px solid #e5eaf0;color:#647083;font-size:12px;line-height:19px;">
                        <strong style="color:#374151;">{{ $appName }}</strong><br>
                        This is an automated account notification.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
