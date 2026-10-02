{{ $appName }}

Hello {{ $name }},

Your HIMS account activation has been cancelled by an administrator.

Reason: {{ $reason }}
@if (filled($details))
Additional Details: {{ $details }}
@endif

If you believe this was done in error, please contact your HIMS administrator.
