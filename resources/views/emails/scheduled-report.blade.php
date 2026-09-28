<x-mail::message>
# {{ $reportTitle }}

HIMS generated this report automatically for its scheduled run at {{ $scheduledFor->format('M d, Y h:i A') }} {{ config('app.timezone') }}.

@if ($filterSummary !== [])
@foreach ($filterSummary as $label => $value)
**{{ $label }}:** {{ $value }}  
@endforeach
@endif

The generated report is attached. This message confirms that HIMS submitted the email to the configured mail service; final delivery depends on that provider.

Regards,  
{{ config('privacy.system_name', 'HIMS') }}
</x-mail::message>
