<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" autocomplete="off" x-data="{ password: '', passwordConfirmation: '' }">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="off" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="off" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required minlength="8"
                            pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
                            title="{{ \App\Rules\PasswordStandard::REQUIREMENTS }}"
                            autocomplete="new-password"
                            x-model="password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password"
                            x-model="passwordConfirmation" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-auth.password-requirements />
        </div>

        <div class="mt-4">
            <label for="privacy_consent" class="flex items-start gap-3 cursor-pointer">
                <input id="privacy_consent"
                       type="checkbox"
                       name="privacy_consent"
                       value="1"
                       {{ old('privacy_consent') ? 'checked' : '' }}
                       required
                       aria-describedby="privacy_consent_error"
                       class="mt-0.5 h-4 w-4 rounded border-neutral-300 dark:border-neutral-700 text-primary-600 focus:ring-primary-500 dark:bg-neutral-900" />
                <span class="text-xs text-neutral-600 dark:text-neutral-400 leading-relaxed">
                    I have read and agree to the
                    <a href="{{ route('privacy.notice', ['return' => url()->current()]) }}" class="text-primary-600 underline hover:text-primary-700 dark:text-primary-400" target="_blank" rel="noopener">Privacy Policy ({{ config('privacy.policy_version', 'v1.0') }})</a>
                    and
                    <a href="{{ route('terms', ['return' => url()->current()]) }}" class="text-primary-600 underline hover:text-primary-700 dark:text-primary-400" target="_blank" rel="noopener">Terms of Use</a>.
                    <span class="text-danger-600 dark:text-danger-400 font-bold" aria-hidden="true">*</span>
                </span>
            </label>
            <x-input-error :messages="$errors->get('privacy_consent')" id="privacy_consent_error" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
