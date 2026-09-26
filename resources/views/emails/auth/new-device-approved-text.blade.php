HIMS Security Notice - New Device Approved

A new device was approved and signed in to your HIMS account:

Device: {{ $deviceSummary }}
@if ($ipAddress)
IP Address: {{ $ipAddress }}
@endif
Approved at: {{ $approvedAt }}

Under the HIMS single-active-session policy, any previous active session was ended.
If you did not authorize this, change your password immediately.
