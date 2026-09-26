<x-guest-layout
    :title="$panel->label().' Device Verification'"
    portal="{{ $panel->value }}"
>
    <div
        class="space-y-6"
        x-data="{
            remainingSeconds: {{ (int) $remainingSeconds }},
            canResend: {{ $remainingSeconds <= 0 ? 'true' : 'false' }},
            trustDevice: true,
            resendCountdown: 60,
            timer: null,

            init() {
                this.timer = setInterval(() => {
                    if (this.resendCountdown > 0) {
                        this.resendCountdown--;
                    } else {
                        this.canResend = true;
                    }
                }, 1000);
            }
        }"
    >
        <header class="text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 ring-8 ring-primary-50/50">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>

            <div class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700">
                Security Verification
            </div>

            <h1 class="mt-3 text-2xl font-bold tracking-tight text-neutral-900">
                Verify New Device
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                You are signing in from an unrecognized device. We sent a 6-digit confirmation code to <span class="font-semibold text-neutral-900">{{ $maskedEmail }}</span>.
            </p>
        </header>

        @if (session('status'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-3 text-xs text-success-700 font-medium">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ $verifyUrl }}" class="space-y-5">
            @csrf

            <div>
                <label for="otp" class="block text-xs font-medium text-neutral-700">
                    6-Digit Security Code
                </label>
                <input
                    id="otp"
                    type="text"
                    name="otp"
                    required
                    maxlength="6"
                    pattern="[0-9]{6}"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    placeholder="000000"
                    class="mt-1.5 block w-full text-center tracking-[0.5em] font-mono text-2xl font-bold rounded-lg border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                />
                @error('otp')
                    <p class="mt-2 text-xs text-danger-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-lg border border-neutral-200 bg-neutral-50/50 p-3 text-xs text-neutral-600">
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input
                        type="checkbox"
                        name="trust_device"
                        value="1"
                        x-model="trustDevice"
                        class="mt-0.5 rounded border-neutral-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span>
                        <strong class="font-medium text-neutral-800">Trust this device for 30 days</strong>
                        <br>
                        <span class="text-neutral-500">Do not ask for verification again on this browser for the next 30 days.</span>
                    </span>
                </label>
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition"
            >
                Confirm Device & Sign In
            </button>
        </form>

        <div class="flex items-center justify-between text-xs border-t border-neutral-200 pt-4">
            <form method="POST" action="{{ $resendUrl }}">
                @csrf
                <button
                    type="submit"
                    :disabled="!canResend"
                    :class="canResend ? 'text-primary-600 hover:text-primary-700 font-medium' : 'text-neutral-400 cursor-not-allowed'"
                    class="transition"
                >
                    <span x-show="canResend">Resend Security Code</span>
                    <span x-show="!canResend" x-text="`Resend code in ${resendCountdown}s`"></span>
                </button>
            </form>

            <a
                href="{{ $loginUrl }}"
                class="text-neutral-500 hover:text-neutral-700 transition"
            >
                Back to Sign-In
            </a>
        </div>
    </div>
</x-guest-layout>
