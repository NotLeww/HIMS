HIMS Security Alert - New Sign-in Attempt

An unrecognized device is attempting to sign in to your HIMS account:

Device: {{ $deviceSummary }}
@if ($ipAddress)
IP Address: {{ $ipAddress }}
@endif
Time: {{ $requestedAt }}

@if ($otp)
Your verification code is: {{ $otp }}
(Expires in {{ $expiresInMinutes }} minutes)
@endif

If you did not initiate this sign-in attempt, someone may have your password. We recommend changing your password immediately.
