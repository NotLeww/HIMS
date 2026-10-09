<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="HIMS connects hospital procurement, central warehouse inventory, and ward replenishment on one operational record.">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
        <title>HIMS | Supply Chain &amp; Inventory Management</title>
        @include('layouts.partials.theme-script')
        @include('layouts.partials.navigation-loading-state')
        @include('layouts.partials.font-loader')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $hasLogin = Route::has('login');
        $dashboardUrl = $hasLogin && \App\Support\AuthenticationContext::authenticatedGuard() !== null
            ? route(\App\Support\AuthenticationContext::dashboardRoute())
            : null;
    @endphp
    <body class="min-h-screen bg-neutral-50 text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
        @include('layouts.partials.loading-overlay')

        <div class="relative min-h-screen overflow-hidden">
            {{-- Hospital campus background integrated with pure neutral gray overlays --}}
            <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                <img src="{{ asset('img/landingpage.jpg') }}" alt="" class="h-full w-full object-cover object-center animate-slow-zoom will-change-transform opacity-30 dark:opacity-20" />
                <div class="absolute inset-0 bg-gradient-to-r from-neutral-50 via-neutral-50/92 to-neutral-50/75 dark:from-neutral-950 dark:via-neutral-950/90 dark:to-neutral-950/70"></div>
                <div class="absolute inset-0 bg-gradient-to-b from-neutral-50/80 via-transparent to-neutral-50 dark:from-neutral-950/80 dark:via-transparent dark:to-neutral-950"></div>
            </div>

            <div class="relative mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 sm:px-6 lg:px-8">
                {{-- Header (Locked height h-20 so it never changes size between pages) --}}
                <header class="flex h-20 shrink-0 items-center justify-between border-b border-neutral-200 dark:border-neutral-800">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-50 dark:focus-visible:ring-offset-neutral-950">
                        <img src="{{ asset('img/hims-logo.png') }}" alt="" class="h-10 w-10 shrink-0 rounded-lg bg-white object-cover ring-1 ring-neutral-200 dark:ring-neutral-700">
                        <span class="min-w-0">
                            <span class="block text-sm sm:text-base font-bold tracking-tight text-neutral-950 dark:text-neutral-50 leading-tight">Dr. Jose N. Rodriguez Memorial Hospital and Sanitarium</span>
                            <span class="block text-[10px] font-semibold uppercase tracking-[0.16em] text-neutral-500 dark:text-neutral-400 mt-0.5">Hospital Operations</span>
                        </span>
                    </a>

                    <div class="flex items-center gap-3">
                        <x-ui.theme-toggle size="sm" />
                        @if ($dashboardUrl)
                            <x-ui.button variant="secondary" size="sm" :href="$dashboardUrl">Dashboard</x-ui.button>
                        @endif
                    </div>
                </header>

                {{-- Hero & Content --}}
                <main class="flex flex-1 flex-col justify-center py-12 sm:py-16 lg:py-20">
                    <div class="max-w-3xl">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-neutral-500 dark:text-neutral-400">
                            Supply Chain &amp; Inventory Management
                        </p>

                        <h1 id="landing-title" class="mt-4 text-balance text-3xl font-extrabold tracking-tight text-neutral-950 dark:text-neutral-50 sm:text-5xl sm:leading-[1.12]">
                            From supply request to ward, every handoff has a record.
                        </h1>

                        <p class="mt-5 max-w-2xl text-base leading-relaxed text-neutral-600 dark:text-neutral-300 sm:text-lg">
                            An accountable operational workflow connecting procurement, central warehouse inventory, and ward replenishment on one verifiable record.
                        </p>

                        <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center">
                            @if ($hasLogin)
                                <a href="{{ $dashboardUrl ?? route('login') }}" class="group inline-flex min-h-12 items-center justify-center gap-3 rounded-lg bg-primary-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 active:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-950">
                                    <x-ui.icon name="lock-closed" class="h-4 w-4 text-white/80 transition group-hover:scale-105" />
                                    <span>{{ $dashboardUrl ? 'Open Dashboard' : 'Log in to HIMS' }}</span>
                                    <x-ui.icon name="arrow-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </a>
                            @endif

                            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                Restricted to authorized hospital personnel
                            </span>
                        </div>
                    </div>

                    {{-- Supply Route Pipeline --}}
                    <section aria-labelledby="route-title" class="mt-14 border-t border-neutral-200 pt-10 dark:border-neutral-800 sm:mt-18">
                        <h2 id="route-title" class="text-[11px] font-semibold uppercase tracking-[0.14em] text-neutral-500 dark:text-neutral-400">
                            The Hospital Supply Route
                        </h2>

                        <ol class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Hospital supply route stages">
                            <li class="rounded-xl border border-neutral-200 bg-white/90 p-5 shadow-xs backdrop-blur-md transition hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/80 dark:hover:border-neutral-700">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-bold text-neutral-500 dark:text-neutral-400">01</span>
                                    <x-ui.icon name="clipboard-document-list" class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                                </div>
                                <h3 class="mt-4 text-sm font-bold text-neutral-900 dark:text-neutral-100">Procurement</h3>
                                <p class="mt-1.5 text-xs leading-relaxed text-neutral-600 dark:text-neutral-400">
                                    Supply requests, supplier quotation matrices, and purchase orders.
                                </p>
                            </li>

                            <li class="rounded-xl border border-neutral-200 bg-white/90 p-5 shadow-xs backdrop-blur-md transition hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/80 dark:hover:border-neutral-700">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-bold text-neutral-500 dark:text-neutral-400">02</span>
                                    <x-ui.icon name="building-storefront" class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                                </div>
                                <h3 class="mt-4 text-sm font-bold text-neutral-900 dark:text-neutral-100">Central Warehouse</h3>
                                <p class="mt-1.5 text-xs leading-relaxed text-neutral-600 dark:text-neutral-400">
                                    Receiving inspection, storage location mapping, and batch &amp; lot control.
                                </p>
                            </li>

                            <li class="rounded-xl border border-neutral-200 bg-white/90 p-5 shadow-xs backdrop-blur-md transition hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/80 dark:hover:border-neutral-700">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-bold text-neutral-500 dark:text-neutral-400">03</span>
                                    <x-ui.icon name="truck" class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                                </div>
                                <h3 class="mt-4 text-sm font-bold text-neutral-900 dark:text-neutral-100">Ward Supply</h3>
                                <p class="mt-1.5 text-xs leading-relaxed text-neutral-600 dark:text-neutral-400">
                                    Stock movement from central stores to care units with dual-custody verification.
                                </p>
                            </li>

                            <li class="rounded-xl border border-neutral-200 bg-white/90 p-5 shadow-xs backdrop-blur-md transition hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/80 dark:hover:border-neutral-700">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-bold text-neutral-500 dark:text-neutral-400">04</span>
                                    <x-ui.icon name="document-check" class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
                                </div>
                                <h3 class="mt-4 text-sm font-bold text-neutral-900 dark:text-neutral-100">Audit &amp; Reporting</h3>
                                <p class="mt-1.5 text-xs leading-relaxed text-neutral-600 dark:text-neutral-400">
                                    Perpetual inventory tracking, actor-attributed logging, and audit-ready reports.
                                </p>
                            </li>
                        </ol>
                    </section>
                </main>

                {{-- Footer --}}
                <footer class="flex flex-col gap-3 border-t border-neutral-200 py-5 text-xs text-neutral-500 dark:border-neutral-800 dark:text-neutral-400 sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; {{ date('Y') }} HIMS &middot; Hospital Operations Platform</p>
                    <div class="flex items-center gap-5 font-medium">
                        <a href="{{ route('privacy.notice') }}" class="transition hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:text-white">Privacy Notice</a>
                        <a href="{{ route('terms') }}" class="transition hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:text-white">Terms of Use</a>
                    </div>
                </footer>
            </div>
        </div>
    </body>
</html>
