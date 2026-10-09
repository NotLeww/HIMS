HIMS Security Alert - New Sign-in Attempt

An unrecognized browser is attempting to sign in to your HIMS account:

Browser: {{ $deviceSummary }}
@if ($ipAddress)
IP Address: {{ $ipAddress }}
@endif
Time: {{ $requestedAt }}

Approve Once: {{ $approveOnceUrl }}
Approve & Trust This Browser: {{ $approveTrustUrl }}
Deny Sign-In: {{ $denyUrl }}

If you did not initiate this sign-in attempt, someone may have your password. We recommend changing your password immediately.
