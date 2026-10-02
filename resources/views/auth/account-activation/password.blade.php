<x-guest-layout title="Create HIMS Password">
    <div class="space-y-7">
        <header>
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">Create your password</h1>
            <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-neutral-400">Your contact is verified. Create the password only you will know to finish activation.</p>
        </header>

        <form method="POST" action="{{ route('activation.password.store') }}" class="space-y-5" autocomplete="off" x-data="{ password: '', passwordConfirmation: '' }">
            @csrf
            <x-ui.field
                id="activation-password"
                name="password"
                label="New password"
                type="password"
                required
                minlength="8"
                pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
                title="{{ \App\Rules\PasswordStandard::REQUIREMENTS }}"
                autocomplete="new-password"
                x-model="password"
            />
            <x-ui.field
                id="activation-password-confirmation"
                name="password_confirmation"
                label="Confirm password"
                type="password"
                required
                autocomplete="new-password"
                x-model="passwordConfirmation"
            />
            <x-auth.password-requirements />
            <x-ui.button type="submit" size="lg" class="w-full" data-loading-text="Activating account...">Activate account</x-ui.button>
        </form>
    </div>
</x-guest-layout>
