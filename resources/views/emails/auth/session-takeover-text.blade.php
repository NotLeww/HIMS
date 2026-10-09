HIMS Security Notice - Session Transferred

Your HIMS session was transferred to a trusted browser:

Browser: {{ $deviceSummary }}
@if ($ipAddress)
IP Address: {{ $ipAddress }}
@endif
Time: {{ $takenOverAt }}

In accordance with the HIMS single-active-session policy, any previous session was signed out.
If this was not you, change your password immediately.
