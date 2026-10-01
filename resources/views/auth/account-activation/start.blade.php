<x-guest-layout title="Activate HIMS Account">
    <div class="space-y-7">
        <header>
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">Activate your account</h1>
            <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-neutral-400">
                Enter both contact details registered by your administrator. We use the same response whether an account is eligible or not.
            </p>
        </header>

        <form method="POST" action="{{ route('activation.identify') }}" class="space-y-5" autocomplete="off">
            @csrf
            <div>
                <x-input-label for="activation-email" value="Registered email address" class="text-neutral-700 dark:text-neutral-300" />
                <x-text-input id="activation-email" class="mt-2 block h-11 w-full rounded-lg border-neutral-300 px-3.5 text-sm dark:border-neutral-700 dark:bg-neutral-800" type="email" name="email" :value="old('email')" required autofocus autocomplete="off" />
                <x-input-error :messages="$errors->get('email')" class="mt-2 text-danger-600" />
            </div>
            <div>
                <x-input-label for="activation-phone" value="Registered mobile number" class="text-neutral-700 dark:text-neutral-300" />
                <x-text-input id="activation-phone" class="mt-2 block h-11 w-full rounded-lg border-neutral-300 px-3.5 text-sm dark:border-neutral-700 dark:bg-neutral-800" type="tel" name="phone" :value="old('phone')" required inputmode="numeric" minlength="11" maxlength="11" pattern="09[0-9]{9}" autocomplete="off" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2 text-danger-600" />
            </div>
            <x-ui.button type="submit" size="lg" class="w-full" data-loading-text="Checking details...">Continue</x-ui.button>
        </form>

        <a class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700" href="{{ route('login') }}">
            <x-ui.icon name="chevron-left" class="h-4 w-4" /> Back to login
        </a>
    </div>
</x-guest-layout>
