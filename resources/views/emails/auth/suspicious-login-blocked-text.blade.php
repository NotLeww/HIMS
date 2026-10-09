HIMS Security Alert - Suspicious Sign-in Blocked

A sign-in request to your HIMS account was rejected and blocked:

Browser: {{ $deviceSummary }}
@if ($ipAddress)
IP Address: {{ $ipAddress }}
@endif
Action: Access denied and browser temporarily blocked for {{ $cooldownMinutes }} minutes.

If you did not initiate this attempt, your password may be known to someone else. We strongly recommend changing your password immediately.
