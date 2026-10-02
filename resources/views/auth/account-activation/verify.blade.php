<x-guest-layout title="Verify Activation Code">
    <div class="space-y-7">
        <header>
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">Enter your verification code</h1>
            <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-neutral-400">
                Enter the 6-digit code sent to {{ $identity['channel'] === 'email' ? $identity['email_mask'] : $identity['phone_mask'] }}.
            </p>
        </header>

        <x-auth-session-status class="rounded-lg border border-success-100 bg-success-50 px-4 py-3 text-success-700" :status="session('status')" />

        <form method="POST" action="{{ route('activation.verify.store') }}" class="space-y-5" autocomplete="off">
            @csrf
            <div>
                <x-input-label for="activation-otp" value="Verification code" class="text-neutral-700 dark:text-neutral-300" />
                <x-text-input id="activation-otp" class="mt-2 block h-11 w-full rounded-lg border-neutral-300 px-3.5 text-center font-mono text-lg tracking-[0.25em] dark:border-neutral-700 dark:bg-neutral-800" type="text" name="otp" :value="old('otp')" required inputmode="numeric" minlength="6" maxlength="6" pattern="[0-9]{6}" autofocus autocomplete="one-time-code" />
                <x-input-error :messages="$errors->get('otp')" class="mt-2 text-danger-600" />
            </div>
            <x-ui.button type="submit" size="lg" class="w-full" data-loading-text="Verifying...">Verify code</x-ui.button>
        </form>

        <form method="POST" action="{{ route('activation.send') }}">
            @csrf
            <input type="hidden" name="channel" value="{{ $identity['channel'] }}">
            <button type="submit" class="text-sm font-medium text-primary-600 hover:text-primary-700">Resend code</button>
        </form>
    </div>
</x-guest-layout>
