<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <title>Privacy Policy Acknowledgment · HIMS</title>
    @include('layouts.partials.theme-script')
    <link rel="preconnect" href="https://fonts.bunny.net">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-neutral-100 dark:bg-neutral-950 font-sans text-neutral-800 dark:text-neutral-200 antialiased selection:bg-primary-600 selection:text-white transition-colors duration-150 flex flex-col justify-center py-8 sm:py-12 px-4 sm:px-6 lg:px-8">
    <div class="mx-auto w-full max-w-2xl">
        {{-- Masthead Header --}}
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-primary-100 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 mb-3 ring-1 ring-primary-200 dark:ring-primary-800">
                <x-ui.icon name="shield-check" class="h-6 w-6" />
            </div>
            <p class="text-xs font-bold uppercase tracking-wider text-neutral-600 dark:text-neutral-400">
                {{ $hospitalName }}
            </p>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-neutral-900 dark:text-white mt-1">
                Privacy Policy Acknowledgment
            </h1>
            <div class="mt-2 flex items-center justify-center gap-2 text-xs font-mono">
                <span class="rounded bg-neutral-200 dark:bg-neutral-800 px-2 py-0.5 text-neutral-700 dark:text-neutral-300">
                    REF: {{ $policyDocumentRef }}
                </span>
                <span class="rounded bg-primary-100 dark:bg-primary-950/80 px-2 py-0.5 text-primary-800 dark:text-primary-300 font-semibold">
                    Version: {{ $policyVersion }}
                </span>
                <span class="rounded bg-neutral-200 dark:bg-neutral-800 px-2 py-0.5 text-neutral-700 dark:text-neutral-300">
                    Effective: {{ $policyEffectiveDate }}
                </span>
            </div>
        </div>

        {{-- Main Document Card --}}
        <div class="rounded-xl border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-md p-6 sm:p-8 space-y-6">
            <div class="border-b border-neutral-200 dark:border-neutral-800 pb-4">
                <h2 class="text-sm font-semibold text-neutral-900 dark:text-white uppercase tracking-wider">
                    Notice to Authorized Personnel
                </h2>
                <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-300 leading-relaxed">
                    Under Republic Act No. 10173 (Data Privacy Act of 2012) and institutional governance standards, your employee user account and operational actions within HIMS are processed to maintain medical supply traceability, pharmacy dispensing records, and audit accountability.
                </p>
            </div>

            {{-- Policy Highlights Accordion/Summary --}}
            <div class="space-y-3 text-xs leading-relaxed text-neutral-600 dark:text-neutral-300">
                <div class="rounded-lg border border-neutral-200 dark:border-neutral-800 p-3.5 bg-neutral-50/60 dark:bg-neutral-800/40 space-y-2">
                    <p class="font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                        <x-ui.icon name="check-circle" class="h-4 w-4 text-primary-600 shrink-0" />
                        <span>Key Processing Terms &amp; Principles:</span>
                    </p>
                    <ul class="list-disc list-inside space-y-1.5 pl-1 text-neutral-700 dark:text-neutral-300">
                        <li><strong>Lawful Basis (RA 10173, Sec. 12):</strong> Processing is necessary for your employment duties, statutory DOH/COA audit compliance, and safeguarding hospital medicine supplies.</li>
                        <li><strong>Audit Trail Accountability:</strong> System interactions, inventory movements, and login events are recorded in an append-only audit trail to maintain chain-of-custody.</li>
                        <li><strong>Security &amp; Encryption:</strong> Sensitive contact information and credentials are encrypted at rest; passwords are stored via one-way cryptographic hashing.</li>
                        <li><strong>Your Data Privacy Rights:</strong> You may inspect, request copies of, or petition for rectification of your employee data by contacting the DPO at <span class="font-mono text-neutral-900 dark:text-neutral-100">{{ $dpoEmail }}</span>.</li>
                    </ul>
                </div>

                <div class="flex items-center justify-between gap-4 pt-1">
                    <a href="{{ route('privacy.notice') }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 underline">
                        <span>Read Full Official System Privacy Notice &bull; {{ $policyDocumentRef }}</span>
                        <x-ui.icon name="arrow-top-right-on-square" class="h-3.5 w-3.5" />
                    </a>
                    <a href="{{ route('terms') }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="text-xs text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 underline">
                        Terms of Use
                    </a>
                </div>
            </div>

            {{-- Consent Form --}}
            <form method="POST" action="{{ route('consent.privacy-policy.store') }}" class="space-y-4 pt-2">
                @csrf
                <input type="hidden" name="policy_version" value="{{ $policyVersion }}">

                @if ($errors->any())
                    <x-ui.alert variant="danger" title="Consent Required" class="mb-4">
                        {{ $errors->first() }}
                    </x-ui.alert>
                @endif

                <label for="privacy_consent"
                       class="flex items-start gap-3 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/60 p-4 cursor-pointer hover:bg-neutral-100/70 dark:hover:bg-neutral-800 transition-colors">
                    <input id="privacy_consent"
                           type="checkbox"
                           name="privacy_consent"
                           value="1"
                           {{ old('privacy_consent') ? 'checked' : '' }}
                           required
                           aria-describedby="consent-description"
                           class="mt-0.5 h-4 w-4 rounded border-neutral-300 dark:border-neutral-600 text-primary-600 focus:ring-primary-500 dark:bg-neutral-900" />
                    <span class="text-xs sm:text-sm text-neutral-800 dark:text-neutral-200 leading-relaxed font-medium">
                        I have read, understand, and agree to the Hospital Inventory Management System
                        <a href="{{ route('privacy.notice') }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 dark:text-primary-400 underline font-semibold hover:text-primary-700">Privacy Policy ({{ $policyVersion }})</a>
                        and
                        <a href="{{ route('terms') }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 dark:text-primary-400 underline font-semibold hover:text-primary-700">Terms of Use</a>.
                        <span class="text-danger-600 font-bold" aria-hidden="true">*</span>
                    </span>
                </label>

                <p id="consent-description" class="text-[11px] text-neutral-500 leading-normal">
                    <strong>Mandatory Acknowledgment:</strong> Consent to the institutional data protection policy is required to access your HIMS account. Your explicit acknowledgment will be recorded with a timestamp and your employee account identity.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-neutral-200 dark:border-neutral-800">
                    <button type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 hover:bg-primary-700 px-5 py-2.5 text-xs sm:text-sm font-semibold text-white shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 transition-colors">
                        <x-ui.icon name="check" class="h-4 w-4" />
                        <span>Acknowledge &amp; Continue</span>
                    </button>
            </form>

                    {{-- Decline Option (Logout) --}}
                    @php
                        $logoutRoute = match (\App\Support\AuthenticationContext::authenticatedGuard()) {
                            \App\Support\AuthenticationContext::SUPER_ADMIN_GUARD => route('super-admin.logout'),
                            \App\Support\AuthenticationContext::ADMIN_GUARD => route('admin.logout'),
                            default => route('logout'),
                        };
                    @endphp
                    <form method="POST" action="{{ $logoutRoute }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-750 px-4 py-2.5 text-xs font-medium text-neutral-700 dark:text-neutral-300 shadow-2xs transition-colors">
                            <x-ui.icon name="arrow-left-on-rectangle" class="h-3.5 w-3.5" />
                            <span>Decline &amp; Sign Out</span>
                        </button>
                    </form>
                </div>
        </div>
    </div>
</body>
</html>
