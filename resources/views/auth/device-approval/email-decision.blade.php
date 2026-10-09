@php
    $decisionContent = match ($decision) {
        'approve-once' => ['Confirm sign-in', 'Approve Once', 'This permits only this sign-in. The browser will remain untrusted.', 'primary'],
        'approve-trust' => ['Confirm and trust browser', 'Approve & Trust Browser', 'This permits the sign-in and trusts this browser profile until its trust expires or is revoked.', 'primary'],
        default => ['Deny sign-in', 'Deny Sign-In', 'This blocks the request and temporarily restricts repeated attempts from this browser context.', 'danger'],
    };
@endphp

<x-guest-layout :title="$decisionContent[0]" portal="{{ $panel->value }}">
    <div class="space-y-5">
        <header>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">{{ $decisionContent[0] }}</h1>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">{{ $decisionContent[2] }}</p>
        </header>

        <dl class="space-y-3 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm dark:border-neutral-800 dark:bg-neutral-900/70">
            <div class="flex items-start justify-between gap-4"><dt class="text-neutral-500 dark:text-neutral-400">Browser</dt><dd class="text-right font-semibold text-neutral-900 dark:text-neutral-100">{{ $approvalRequest->device_name ?: 'Unknown browser' }}</dd></div>
            <div class="flex items-start justify-between gap-4"><dt class="text-neutral-500 dark:text-neutral-400">Network</dt><dd class="font-mono text-neutral-800 dark:text-neutral-200">{{ $approvalRequest->ip_address ?: 'Unavailable' }}</dd></div>
            <div class="flex items-start justify-between gap-4"><dt class="text-neutral-500 dark:text-neutral-400">Requested</dt><dd class="text-right text-neutral-800 dark:text-neutral-200">{{ $approvalRequest->requested_at->timezone(config('app.timezone', 'UTC'))->format('M d, Y h:i A') }}</dd></div>
        </dl>

        @if ($available)
            <form method="POST" action="{{ $confirmUrl }}" class="space-y-3">
                @csrf
                <button type="submit" data-loading-text="Confirming..." class="w-full rounded-lg px-4 py-3 text-sm font-semibold text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900 {{ $decisionContent[3] === 'danger' ? 'bg-danger-600 hover:bg-danger-700 focus-visible:ring-danger-500' : 'bg-primary-600 hover:bg-primary-700 focus-visible:ring-primary-500' }}">
                    {{ $decisionContent[1] }}
                </button>
                <a href="{{ route($panel->loginRoute()) }}" class="block w-full rounded-lg border border-neutral-300 px-4 py-3 text-center text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800">Cancel</a>
            </form>
        @else
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-amber-900/70 dark:bg-amber-950/50 dark:text-amber-200">
                This request is expired, cancelled, or has already been decided.
            </div>
        @endif
    </div>
</x-guest-layout>
