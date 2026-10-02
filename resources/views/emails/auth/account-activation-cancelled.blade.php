<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HIMS account activation cancelled</title>
</head>
<body style="margin:0;background:#faf5f5;font-family:'Segoe UI',Tahoma,sans-serif;color:#172033;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#faf5f5;padding:36px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #eadada;">
                <tr>
                    <td style="padding:26px 32px;background:#991b1b;color:#ffffff;">
                        <div style="font-size:21px;font-weight:700;letter-spacing:-0.3px;">HIMS</div>
                        <div style="margin-top:4px;color:#fecaca;font-size:11px;letter-spacing:1.1px;text-transform:uppercase;">Account Services</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <div style="display:inline-block;padding:6px 10px;border-radius:999px;background:#fef2f2;color:#991b1b;font-size:12px;font-weight:700;">Activation cancelled</div>
                        <h1 style="margin:18px 0 0;font-size:26px;line-height:34px;letter-spacing:-0.5px;color:#172033;">Account activation cancelled</h1>
                        <p style="margin:14px 0 0;color:#4b5565;font-size:15px;line-height:24px;">Hello {{ $name }}, your HIMS account activation has been cancelled by an administrator.</p>

                        <div style="margin:24px 0;padding:18px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;">
                            <div style="color:#7f1d1d;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;">Cancellation reason</div>
                            <div style="margin-top:7px;color:#450a0a;font-size:15px;font-weight:700;line-height:23px;">{{ $reason }}</div>
                            @if (filled($details))
                                <div style="margin-top:14px;padding-top:14px;border-top:1px solid #fecaca;color:#7f1d1d;font-size:13px;line-height:21px;">
                                    <strong>Additional details</strong><br>
                                    {{ $details }}
                                </div>
                            @endif
                        </div>

                        <div style="padding:18px;background:#f8fafc;border:1px solid #dbe3ec;border-radius:10px;">
                            <div style="font-size:15px;font-weight:700;color:#172033;">Questions about this cancellation?</div>
                            @if (filled($creatorEmail))
                                <div style="margin-top:6px;color:#4b5565;font-size:13px;line-height:21px;">Contact the administrator who created your account:</div>
                                <a href="mailto:{{ $creatorEmail }}" style="display:inline-block;margin-top:10px;color:#174c86;font-size:14px;font-weight:700;line-height:21px;text-decoration:underline;text-underline-offset:3px;word-break:break-all;">{{ $creatorEmail }}</a>
                            @else
                                <div style="margin-top:6px;color:#4b5565;font-size:13px;line-height:21px;">Please contact your HIMS administrator for assistance.</div>
                            @endif
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px;background:#fffafa;border-top:1px solid #f0e2e2;color:#715f5f;font-size:12px;line-height:19px;">
                        <strong style="color:#4b3434;">{{ $appName }}</strong><br>
                        This is an automated account notification.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
