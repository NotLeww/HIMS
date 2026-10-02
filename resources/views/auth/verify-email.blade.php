@php
    $verificationPanel = \App\Support\AuthenticationPanel::forGuard(
        \App\Support\AuthenticationContext::authenticatedGuard() ?? \App\Support\AuthenticationContext::WEB_GUARD
    );
    $portal = $verificationPanel === \App\Support\AuthenticationPanel::SuperAdmin
        ? 'super-admin'
        : $verificationPanel->value;
@endphp

<x-guest-layout :portal="$portal" title="Verify Email">
    <h1 class="text-xl font-bold tracking-tight text-neutral-950 dark:text-neutral-50">Verify your email address</h1>
    <p class="mt-2 text-sm leading-6 text-neutral-600 dark:text-neutral-300">
        {{ __('Open the verification link we sent to your email address. The link is valid for :minutes minutes.', ['minutes' => config('auth.verification.expire', 60)]) }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <x-ui.alert class="mt-5" variant="success" title="Email sent" message="A new verification link has been sent to your email address." />
    @endif

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-ui.button type="submit" data-loading-text="Sending verification email...">
                {{ __('Send New Verification Email') }}
            </x-ui.button>
        </form>

        <form method="POST" action="{{ route(\App\Support\AuthenticationContext::logoutRoute()) }}"
              data-manual-logout
              data-confirm-title="Confirm logout"
              data-confirm-message="Are you sure you want to log out?"
              data-confirm-label="Log Out">
            @csrf

            <button type="submit" class="rounded-md text-sm font-semibold text-neutral-600 underline underline-offset-4 hover:text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:text-neutral-300 dark:hover:text-white dark:focus:ring-offset-neutral-900">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
