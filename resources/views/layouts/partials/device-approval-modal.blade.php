<div
    x-data="{
        isOpen: false,
        pendingRequest: null,
        processingAction: null,
        isPolling: false,
        pollInterval: null,
        csrfToken: '{{ csrf_token() }}',
        pendingCheckUrl: '{{ route('auth.device-approvals.pending') }}',
        loginUrl: '{{ route(\App\Support\AuthenticationContext::loginRoute(\App\Support\AuthenticationContext::authenticatedGuard() ?? 'web')) }}',

        init() {
            this.checkForPending();
            this.startPolling();

            // React across browser tabs if session replaced
            window.addEventListener('storage', (e) => {
                if (e.key === 'hims:session:replaced') {
                    window.location.href = this.loginUrl;
                }
            });

            // If visibility changes to visible, poll immediately
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && !this.isOpen) {
                    this.checkForPending();
                }
            });
        },

        startPolling() {
            this.pollInterval = setInterval(() => {
                if (!document.hidden && !this.isOpen) {
                    this.checkForPending();
                }
            }, 10000);
        },

        destroy() {
            if (this.pollInterval) clearInterval(this.pollInterval);
        },

        notify(type, title, message) {
            window.dispatchEvent(new CustomEvent('toast', {
                detail: { type, title, message }
            }));
        },

        async checkForPending() {
            if (this.isPolling) return;
            this.isPolling = true;

            try {
                const response = await fetch(this.pendingCheckUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Session-Activity': 'passive'
                    }
                });

                if (response.status === 401) {
                    // Session might have been superseded
                    if (response.headers.get('X-Session-Replaced')) {
                        localStorage.setItem('hims:session:replaced', Date.now().toString());
                        window.location.href = this.loginUrl;
                    }
                    return;
                }

                if (!response.ok) return;

                const data = await response.json();
                if (data.has_pending && data.request) {
                    this.pendingRequest = data.request;
                    this.isOpen = true;
                } else if (this.isOpen && !data.has_pending) {
                    // Request was cancelled or expired elsewhere
                    this.isOpen = false;
                    this.pendingRequest = null;
                }
            } catch (e) {
                // Silently ignore network hiccup during poll
            } finally {
                this.isPolling = false;
            }
        },

        async approve(trustDevice = false) {
            if (!this.pendingRequest || this.processingAction) return;
            this.processingAction = trustDevice ? 'approve-trust' : 'approve-once';

            try {
                const response = await fetch(this.pendingRequest.approve_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ trust_device: trustDevice })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    localStorage.setItem('hims:session:replaced', Date.now().toString());
                    window.location.href = data.redirect_url || this.loginUrl;
                } else {
                    this.notify('error', 'ERROR', data.message || 'Failed to approve the sign-in request.');
                    this.processingAction = null;
                }
            } catch (e) {
                this.notify('error', 'ERROR', 'A network error occurred while approving the sign-in request.');
                this.processingAction = null;
            }
        },

        async reject() {
            if (!this.pendingRequest || this.processingAction) return;
            this.processingAction = 'reject';

            try {
                const response = await fetch(this.pendingRequest.reject_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                this.processingAction = null;

                if (response.ok && data.success) {
                    this.isOpen = false;
                    this.pendingRequest = null;
                    this.notify(
                        'warning',
                        'WARNING',
                        data.security_notice || 'The unrecognized sign-in attempt was rejected and temporarily blocked.'
                    );
                } else {
                    this.notify('error', 'ERROR', data.message || 'Failed to reject the sign-in request.');
                }
            } catch (e) {
                this.processingAction = null;
                this.notify('error', 'ERROR', 'A network error occurred while rejecting the sign-in request.');
            }
        }
    }"
    x-cloak
    x-show="isOpen"
    class="relative z-50"
    role="dialog"
    aria-modal="true"
    aria-labelledby="device-approval-title"
    aria-describedby="device-approval-description"
>
    {{-- Backdrop --}}
    <div
        x-show="isOpen"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-neutral-900/60 dark:bg-neutral-950/80 backdrop-blur-xs transition-opacity"
    ></div>

    <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-20">
        <div
            x-show="isOpen"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="mx-auto max-w-lg transform overflow-hidden rounded-2xl bg-white dark:bg-neutral-900 p-6 text-left shadow-2xl transition-all border border-neutral-200 dark:border-neutral-800"
        >
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 ring-8 ring-amber-50/50 dark:ring-amber-950/20">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:text-amber-300">
                        New Sign-In Request
                    </div>
                    <h3 id="device-approval-title" class="mt-2 text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                        Is this you?
                    </h3>
                    <p id="device-approval-description" class="mt-1 text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed">
                        An unrecognized device entered the correct password for your account and is requesting access.
                    </p>
                </div>
            </div>

            <div class="mt-5 rounded-xl border border-neutral-200/80 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-950/40 p-4 text-xs space-y-2.5 text-neutral-600 dark:text-neutral-400">
                <div class="flex items-center justify-between">
                    <span class="text-neutral-500">Device & Browser:</span>
                    <span class="font-semibold text-neutral-900 dark:text-neutral-200" x-text="pendingRequest ? `${pendingRequest.browser} on ${pendingRequest.platform}` : 'Unknown'"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-neutral-500">Network address:</span>
                    <span class="font-mono text-neutral-900 dark:text-neutral-200" x-text="pendingRequest?.ip_address || 'Unknown'"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-neutral-500">Requested:</span>
                    <span class="text-neutral-900 dark:text-neutral-200" x-text="pendingRequest ? `${pendingRequest.time_ago} (${pendingRequest.requested_at})` : 'Just now'"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-neutral-500">Device Status:</span>
                    <span class="font-medium text-amber-700 dark:text-amber-400">Unrecognized Device</span>
                </div>
            </div>

            <div class="mt-4 rounded-lg bg-neutral-100/70 dark:bg-neutral-800/50 p-3 text-xs text-neutral-600 dark:text-neutral-400 leading-relaxed">
                <span class="font-medium text-neutral-800 dark:text-neutral-200">Notice:</span>
                HIMS enforces single-active-device access. Approving will transfer your session and log out this device immediately.
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    :disabled="processingAction !== null"
                    :aria-busy="processingAction === 'reject'"
                    @click="reject()"
                    class="inline-flex justify-center items-center rounded-lg border border-danger-300 dark:border-danger-800/60 bg-white dark:bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-danger-700 dark:text-danger-400 hover:bg-danger-50 dark:hover:bg-danger-950/40 focus:outline-none focus:ring-2 focus:ring-danger-500 transition"
                >
                    <span x-show="processingAction !== 'reject'">No, this is not me</span>
                    <span x-show="processingAction === 'reject'" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Rejecting...
                    </span>
                </button>
                <button
                    type="button"
                    :disabled="processingAction !== null"
                    :aria-busy="processingAction === 'approve-once'"
                    @click="approve(false)"
                    class="inline-flex justify-center items-center rounded-lg border border-primary-300 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500 transition dark:border-primary-800 dark:bg-neutral-900 dark:text-primary-300"
                >
                    <span x-show="processingAction !== 'approve-once'">Yes, approve sign-in once</span>
                    <span x-show="processingAction === 'approve-once'" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Approving...
                    </span>
                </button>
                <button
                    type="button"
                    :disabled="processingAction !== null"
                    :aria-busy="processingAction === 'approve-trust'"
                    @click="approve(true)"
                    class="inline-flex justify-center items-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                    <span x-show="processingAction !== 'approve-trust'">Yes, approve and trust device</span>
                    <span x-show="processingAction === 'approve-trust'" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Approving...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
