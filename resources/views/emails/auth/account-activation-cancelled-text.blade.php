{{ $appName }}

Hello {{ $name }},

Your HIMS account activation has been cancelled by an administrator.

Reason: {{ $reason }}
@if (filled($details))
Additional Details: {{ $details }}
@endif

Questions about this cancellation?
@if (filled($creatorEmail))
Contact the administrator who created your account: {{ $creatorEmail }}
@else
Please contact your HIMS administrator for assistance.
@endif
