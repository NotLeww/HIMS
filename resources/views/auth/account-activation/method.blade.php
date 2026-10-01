<x-guest-layout title="Choose Activation Method">
    <div class="space-y-7">
        <header>
            <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">Choose where to receive your code</h1>
            <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-neutral-400">Only masked registered contact details are shown.</p>
        </header>

        <form method="POST" action="{{ route('activation.send') }}" class="space-y-4">
            @csrf
            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                <input type="radio" name="channel" value="email" required class="text-primary-600 focus:ring-primary-500" @checked(old('channel', 'email') === 'email')>
                <span><span class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">Email OTP</span><span class="text-sm text-neutral-500 dark:text-neutral-400">{{ $identity['email_mask'] }}</span></span>
            </label>
            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                <input type="radio" name="channel" value="sms" required class="text-primary-600 focus:ring-primary-500" @checked(old('channel') === 'sms')>
                <span><span class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">SMS OTP</span><span class="text-sm text-neutral-500 dark:text-neutral-400">{{ $identity['phone_mask'] }}</span></span>
            </label>
            <x-input-error :messages="$errors->get('channel')" class="text-danger-600" />
            <x-ui.button type="submit" size="lg" class="w-full" data-loading-text="Sending code...">Send OTP</x-ui.button>
        </form>
    </div>
</x-guest-layout>
