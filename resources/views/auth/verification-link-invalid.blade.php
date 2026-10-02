@php($portal = $panel === \App\Support\AuthenticationPanel::SuperAdmin ? 'super-admin' : $panel->value)

<x-guest-layout :portal="$portal" title="Activation Link Unavailable">
    <h1 class="text-xl font-bold tracking-tight text-neutral-950 dark:text-neutral-50">
        {{ $expired ? 'Activation link expired' : 'Activation link invalid' }}
    </h1>

    <x-ui.alert class="mt-5" variant="warning" title="We could not activate this account">
        {{ $expired
            ? 'This activation link has expired. Ask a HIMS administrator to resend the activation email.'
            : 'This activation link is invalid or has already been replaced. Ask a HIMS administrator to resend the activation email.' }}
    </x-ui.alert>

    <x-ui.button class="mt-6" :href="route($panel->loginRoute())">
        Back to {{ $panel->label() }} Login
    </x-ui.button>
</x-guest-layout>
