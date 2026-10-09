<x-guest-layout
    :title="$panel->label().' Browser Approval'"
    portal="{{ $panel->value }}"
>
    <div
        class="space-y-6"
        x-data="{
            status: '{{ $approvalRequest->status }}',
            remainingSeconds: {{ (int) $remainingSeconds }},
            statusUrl: '{{ $statusUrl }}',
            claimUrl: '{{ $claimUrl }}',
            cancelUrl: '{{ $cancelUrl }}',
            loginUrl: '{{ $loginUrl }}',
            isSubmittingClaim: false,
            isPollingStatus: false,
            pollTimer: null,
            countdownTimer: null,
            errorMessage: '',

            init() {
                if (this.status === 'pending') {
                    this.startCountdown();
                    this.startPolling();
                    this.checkStatus();
                } else if (this.status === 'approved') {
                    this.submitClaim();
                }
            },

            startCountdown() {
                this.countdownTimer = setInterval(() => {
                    if (this.remainingSeconds > 0) {
                        this.remainingSeconds--;
                    } else {
                        this.status = 'expired';
                        this.stopPolling();
                    }
                }, 1000);
            },

            startPolling() {
                this.pollTimer = setInterval(() => {
                    if (document.hidden) return;
                    this.checkStatus();
                }, {{ (int) $pollIntervalMilliseconds }});
            },

            stopPolling() {
                if (this.pollTimer) clearInterval(this.pollTimer);
                if (this.countdownTimer) clearInterval(this.countdownTimer);
                this.pollTimer = null;
                this.countdownTimer = null;
            },

            destroy() {
                this.stopPolling();
            },

            async checkStatus() {
                if (this.isPollingStatus) return;
                this.isPollingStatus = true;

                try {
                    const response = await fetch(this.statusUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.redirected) {
                        window.location.href = response.url;
                        return;
                    }

                    if (!response.ok) return;

                    const data = await response.json();
                    if (data.status && data.status !== this.status) {
                        this.status = data.status;
                        if (data.status === 'approved') {
                            this.stopPolling();
                            this.submitClaim();
                        } else if (['rejected', 'expired', 'cancelled', 'completed'].includes(data.status)) {
                            this.stopPolling();
                        }
                    }
                } catch (e) {
                    console.error('Failed to poll approval status', e);
                } finally {
                    this.isPollingStatus = false;
                }
            },

            async submitClaim() {
                if (this.isSubmittingClaim) return;
                this.isSubmittingClaim = true;

                try {
                    const response = await fetch(this.claimUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({})
                    });

                    const data = await response.json();
                    if (response.ok && data.success && data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        this.errorMessage = data.message || 'Failed to establish active session.';
                        this.isSubmittingClaim = false;
                    }
                } catch (e) {
                    this.errorMessage = 'Network error while establishing session. Please refresh.';
                    this.isSubmittingClaim = false;
                }
            },

            formatTime(seconds) {
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                return `${m}:${s.toString().padStart(2, '0')}`;
            }
        }"
    >
        <!-- Pending State -->
        <template x-if="status === 'pending'">
            <div class="space-y-6">
                <header class="text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 ring-8 ring-primary-50/50">
                        <svg class="h-7 w-7 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>

                    <div class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700">
                        <span class="h-2 w-2 rounded-full bg-primary-500 animate-ping"></span>
                        Waiting for Approval
                    </div>

                    <h1 class="mt-3 text-2xl font-bold tracking-tight text-neutral-900">
                        {{ $approvalEmailsEnabled ? 'Check Your Email' : 'Check Your Active Browser' }}
                    </h1>
                    <p class="mt-2 text-sm leading-relaxed text-neutral-600">
                        @if ($approvalEmailsEnabled)
                            We sent a sign-in approval request to your registered email. If another trusted session is active, you can also approve it there.
                        @else
                            Approve this sign-in request from a browser where your account is already active. Without an active session, this request cannot be completed while approval emails are disabled.
                        @endif
                    </p>
                </header>

                <div class="rounded-xl border border-neutral-200/80 bg-neutral-50/50 p-4 text-xs text-neutral-600 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-500">Requesting Browser:</span>
                        <span class="font-medium text-neutral-900">{{ $approvalRequest->device_name ?: 'Web Browser' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-500">Time remaining:</span>
                        <span class="font-mono font-semibold text-neutral-900" x-text="formatTime(remainingSeconds)"></span>
                    </div>
                </div>

                @if ($approvalEmailsEnabled)
                    <form method="POST" action="{{ $resendUrl }}">
                        @csrf
                        <button type="submit" data-loading-text="Resending..." class="w-full rounded-lg border border-primary-300 bg-primary-50 px-4 py-2.5 text-sm font-semibold text-primary-700 transition hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-950/50 dark:text-primary-300">
                            Resend Approval Email
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ $cancelUrl }}">
                    @csrf
                    <button
                        type="submit"
                        class="w-full inline-flex justify-center items-center rounded-lg border border-neutral-300 bg-white px-4 py-2.5 text-sm font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition"
                    >
                        Cancel Request & Return to Sign-In
                    </button>
                </form>
            </div>
        </template>

        <!-- Approved State -->
        <template x-if="status === 'approved'">
            <div class="space-y-6 text-center py-4">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-success-50 text-success-600 ring-8 ring-success-50/50">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-neutral-900">Sign-In Approved!</h2>
                    <p class="mt-2 text-sm text-neutral-600">
                        Transferring your active session to this browser...
                    </p>
                </div>

                <template x-if="errorMessage">
                    <div class="rounded-lg bg-danger-50 p-3 text-xs text-danger-700" x-text="errorMessage"></div>
                </template>

                <div class="flex justify-center">
                    <svg class="animate-spin h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
        </template>

        <!-- Rejected State -->
        <template x-if="status === 'rejected'">
            <div class="space-y-6 text-center py-2">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-danger-50 text-danger-700 ring-8 ring-danger-50/50 dark:bg-rose-950/70 dark:text-rose-300 dark:ring-rose-950/40">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>

                <div>
                    <div class="inline-flex items-center gap-1.5 rounded-full border border-danger-200 bg-danger-50 px-3 py-1 text-xs font-semibold text-danger-800 dark:border-rose-800 dark:bg-rose-950/70 dark:text-rose-200">
                        Sign-In Denied
                    </div>
                    <h2 class="mt-3 text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                        Request Rejected
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">
                        This sign-in request was rejected by the account owner.
                    </p>
                </div>

                <div class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-left text-xs text-danger-900 dark:border-rose-800/80 dark:bg-rose-950/60 dark:text-rose-100">
                    <p class="font-semibold">Temporary Security Cooldown Active</p>
                    <p class="mt-1 leading-relaxed text-danger-800 dark:text-rose-200">
                        To protect the account, further sign-in attempts from this unrecognized browser are temporarily suspended for 15 minutes.
                    </p>
                </div>

                <a
                    href="{{ $loginUrl }}"
                    class="block w-full rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-white dark:focus-visible:ring-offset-neutral-900"
                >
                    Return to Sign-In
                </a>
            </div>
        </template>

        <!-- Expired State -->
        <template x-if="status === 'expired'">
            <div class="space-y-6 text-center py-2">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 ring-8 ring-amber-50/50">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-neutral-900">
                        Request Expired
                    </h2>
                    <p class="mt-2 text-sm text-neutral-600 leading-relaxed">
                        The approval request timed out before receiving a response from your active browser.
                    </p>
                </div>

                <a
                    href="{{ $loginUrl }}"
                    class="block w-full rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-neutral-800 transition"
                >
                    Return to Sign-In
                </a>
            </div>
        </template>

        <!-- Cancelled State -->
        <template x-if="status === 'cancelled'">
            <div class="space-y-6 text-center py-2">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-neutral-900">
                        Request Cancelled
                    </h2>
                    <p class="mt-2 text-sm text-neutral-600">
                        This sign-in attempt was cancelled.
                    </p>
                </div>

                <a
                    href="{{ $loginUrl }}"
                    class="block w-full rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-neutral-800 transition"
                >
                    Return to Sign-In
                </a>
            </div>
        </template>
    </div>
</x-guest-layout>
