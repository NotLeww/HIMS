@php
    // GuestLayout is a class-based component with no properties, so any
    // title="..." passed by a page arrives as a plain attribute.
    $pageTitle = trim((string) $attributes->get('title'));
    $portal = (string) $attributes->get('portal', 'staff');
    $isStaffPortal = $portal === 'staff';
    $isAdminPortal = $portal === 'admin';
    $isSuperAdminPortal = $portal === 'super-admin';
    $isThemeAwarePortal = $isSuperAdminPortal || $portal === 'staff';
    $portalCardClass = match (true) {
        $isStaffPortal => 'border-neutral-200/90 bg-neutral-100/95 dark:bg-neutral-900 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-950/5 backdrop-blur-md dark:border-neutral-800 dark:shadow-black/50',
        $isSuperAdminPortal => 'border-neutral-200/90 bg-white/95 shadow-xl shadow-neutral-900/5 ring-1 ring-neutral-950/5 backdrop-blur-md dark:border-neutral-800 dark:bg-neutral-900/95 dark:shadow-black/50',
        default => 'border-primary-200 bg-neutral-50/95 dark:border-primary-300/30 dark:bg-neutral-900',
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <title>{{ $pageTitle !== '' ? $pageTitle.' · HIMS' : 'HIMS' }}</title>

        {{-- Early zero-flicker theme script --}}
        @include('layouts.partials.theme-script')

        @include('layouts.partials.navigation-loading-state')

        {{-- Inter is pulled in by app.css; this just warms the connection. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen font-sans text-neutral-800 antialiased {{ $isThemeAwarePortal ? 'bg-neutral-50 dark:bg-neutral-950 dark:text-neutral-100' : 'bg-neutral-950' }}">
        <a href="#main-content"
           class="sr-only fixed left-4 top-4 z-[100] rounded-md bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-lg focus:not-sr-only focus:outline-none focus:ring-2 focus:ring-primary-500">
            Skip to main content
        </a>

        @include('layouts.partials.loading-overlay')

        <div class="relative min-h-screen overflow-hidden {{ $isThemeAwarePortal ? 'bg-neutral-50 dark:bg-neutral-950' : '' }}">
            {{-- Modern institutional backdrop matching the landing page --}}
            <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                @if ($isThemeAwarePortal)
                    <img src="{{ asset('img/landingpage.jpg') }}" alt="" class="h-full w-full object-cover object-center animate-slow-zoom will-change-transform opacity-30 dark:opacity-20" />
                    <div class="absolute inset-0 bg-gradient-to-r from-neutral-50 via-neutral-50/92 to-neutral-50/75 dark:from-neutral-950 dark:via-neutral-950/90 dark:to-neutral-950/70"></div>
                    <div class="absolute inset-0 bg-gradient-to-b from-neutral-50/80 via-transparent to-neutral-50 dark:from-neutral-950/80 dark:via-transparent dark:to-neutral-950"></div>
                @elseif ($isAdminPortal)
                    <img src="{{ asset('img/landingpage.jpg') }}" alt="" class="h-full w-full object-cover object-center animate-slow-zoom will-change-transform opacity-25" />
                    <div class="absolute inset-0 bg-primary-950/80"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-primary-950 via-primary-950/90 to-neutral-950/70"></div>
                    <div class="absolute inset-0 bg-gradient-to-b from-neutral-950/40 via-transparent to-neutral-950/85"></div>
                    <div class="absolute -left-24 top-20 h-80 w-80 animate-float-slow rounded-full bg-primary-500/25 blur-3xl will-change-transform"></div>
                    <div class="absolute right-12 top-1/3 h-72 w-72 animate-drift-slow rounded-full bg-primary-300/15 blur-3xl will-change-transform"></div>
                @else
                    <img src="{{ asset('img/landingpage.jpg') }}" alt="" class="h-full w-full object-cover object-center animate-slow-zoom will-change-transform opacity-20" />
                    <div class="absolute inset-0 bg-neutral-950/75"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-neutral-950 via-neutral-950/85 to-neutral-950/65"></div>
                    <div class="absolute inset-0 bg-gradient-to-b from-neutral-950/55 via-transparent to-neutral-950/80"></div>
                    <div class="absolute -left-24 top-20 h-80 w-80 animate-float-slow rounded-full bg-primary-600/20 blur-3xl will-change-transform"></div>
                    <div class="absolute right-12 top-1/3 h-72 w-72 animate-drift-slow rounded-full bg-primary-400/10 blur-3xl will-change-transform"></div>
                @endif
            </div>

            <div class="relative mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 sm:px-6 lg:px-8">
                <header class="flex h-20 shrink-0 items-center justify-between border-b {{ $isThemeAwarePortal ? 'border-neutral-200 dark:border-neutral-800' : 'border-white/10' }}">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 {{ $isThemeAwarePortal ? 'focus-visible:ring-offset-neutral-50 dark:focus-visible:ring-offset-neutral-950' : 'focus-visible:ring-offset-neutral-950' }}">
                        <img src="{{ asset('img/hims-logo.png') }}" alt="" class="h-10 w-10 shrink-0 rounded-lg bg-white object-cover ring-1 ring-neutral-200 dark:ring-neutral-700" />
                        <span class="min-w-0">
                            <span class="block text-sm sm:text-base font-bold tracking-tight {{ $isThemeAwarePortal ? 'text-neutral-950 dark:text-neutral-50' : 'text-white' }} leading-tight">Dr. Jose N. Rodriguez Memorial Hospital and Sanitarium</span>
                            <span class="block text-[10px] font-semibold uppercase tracking-[0.16em] {{ $isThemeAwarePortal ? 'text-neutral-500 dark:text-neutral-400' : 'text-neutral-400' }} mt-0.5">
                                @if ($isSuperAdminPortal)
                                    System Authority
                                @elseif ($isAdminPortal)
                                    Administration Portal
                                @else
                                    Hospital Operations
                                @endif
                            </span>
                        </span>
                    </a>

                    <div class="flex items-center gap-3">
                        @if ($isThemeAwarePortal)
                            <x-ui.theme-toggle size="sm" />
                        @else
                            <x-ui.theme-toggle size="sm" class="text-neutral-300 hover:text-white hover:bg-white/10" />
                        @endif

                        <a href="{{ url('/') }}" class="group inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $isThemeAwarePortal ? 'text-neutral-700 hover:bg-neutral-200/60 hover:text-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-white' : 'text-neutral-300 hover:bg-white/10 hover:text-white' }}">
                            <x-ui.icon name="chevron-left" class="h-3.5 w-3.5 transition-transform duration-300 group-hover:-translate-x-0.5" />
                            Back to home
                        </a>
                    </div>
                </header>

                <main id="main-content" tabindex="-1" class="grid min-w-0 grid-cols-[minmax(0,1fr)] flex-1 items-center gap-10 py-8 lg:grid-cols-[minmax(0,1fr)_29rem] lg:gap-14 lg:py-12 xl:gap-20">
                    <section class="hidden max-w-xl lg:block">
                        @if ($isSuperAdminPortal)
                            <h1 class="animate-fade-up text-balance text-4xl font-extrabold leading-tight tracking-tight text-neutral-950 [animation-delay:240ms] dark:text-white xl:text-5xl">
                                System-wide governance, secured at the highest level.
                            </h1>
                            <p class="mt-4 max-w-lg animate-fade-up text-base leading-7 text-neutral-600 [animation-delay:360ms] dark:text-neutral-300">
                                Control administrative boundaries, protect privileged access, and maintain accountability across HIMS.
                            </p>
                            <div class="mt-8 grid max-w-lg grid-cols-3 gap-3 animate-fade-up [animation-delay:460ms]">
                                <div class="border-l-2 border-primary-500/60 pl-3">
                                    <p class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Access governance</p>
                                    <p class="mt-0.5 text-[11px] leading-4 text-neutral-500 dark:text-neutral-400">System roles</p>
                                </div>
                                <div class="border-l-2 border-primary-500/60 pl-3">
                                    <p class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Security control</p>
                                    <p class="mt-0.5 text-[11px] leading-4 text-neutral-500 dark:text-neutral-400">Protected settings</p>
                                </div>
                                <div class="border-l-2 border-primary-500/60 pl-3">
                                    <p class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Audit oversight</p>
                                    <p class="mt-0.5 text-[11px] leading-4 text-neutral-500 dark:text-neutral-400">Accountability</p>
                                </div>
                            </div>
                        @elseif ($isAdminPortal)
                            <p class="inline-flex animate-fade-up items-center gap-2 rounded-full border border-primary-300/25 bg-primary-400/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.14em] text-primary-200 [animation-delay:120ms]">
                                <x-ui.icon name="users" class="h-3.5 w-3.5" />
                                HIMS administration
                            </p>
                            <h1 class="mt-5 animate-fade-up text-balance text-4xl font-extrabold leading-tight tracking-tight text-white [animation-delay:240ms] xl:text-5xl">
                                Keep hospital operations organized and accountable.
                            </h1>
                            <p class="mt-4 max-w-lg animate-fade-up text-base leading-7 text-primary-100/85 [animation-delay:360ms]">
                                Manage authorized users and operational workflows from a focused administration workspace.
                            </p>
                            <div class="mt-8 grid max-w-lg grid-cols-3 gap-3 animate-fade-up [animation-delay:460ms]">
                                <div class="rounded-lg border border-white/10 bg-white/5 p-3 backdrop-blur-sm">
                                    <x-ui.icon name="users" class="h-4 w-4 text-primary-300" />
                                    <p class="mt-2 text-xs font-semibold text-white">User access</p>
                                </div>
                                <div class="rounded-lg border border-white/10 bg-white/5 p-3 backdrop-blur-sm">
                                    <x-ui.icon name="clipboard-document-list" class="h-4 w-4 text-primary-300" />
                                    <p class="mt-2 text-xs font-semibold text-white">Operations</p>
                                </div>
                                <div class="rounded-lg border border-white/10 bg-white/5 p-3 backdrop-blur-sm">
                                    <x-ui.icon name="document-text" class="h-4 w-4 text-primary-300" />
                                    <p class="mt-2 text-xs font-semibold text-white">Audit records</p>
                                </div>
                            </div>
                        @else
                            <p class="inline-flex animate-fade-up items-center gap-2 rounded-md border border-neutral-200 bg-neutral-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-neutral-700 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300 [animation-delay:120ms]">
                                <x-ui.icon name="building-office-2" class="h-3.5 w-3.5" />
                                Hospital Operations Portal
                            </p>
                            <h1 class="mt-4 animate-fade-up text-balance text-4xl font-extrabold leading-tight tracking-tight text-neutral-950 dark:text-neutral-50 [animation-delay:240ms] xl:text-5xl">
                                Hospital supply operations, on one secure record.
                            </h1>
                            <p class="mt-4 max-w-lg animate-fade-up text-base leading-7 text-neutral-600 dark:text-neutral-400 [animation-delay:360ms]">
                                Sign in to manage procurement, central warehouse inventory, and ward replenishment with end-to-end custody tracking.
                            </p>
                            <div class="mt-8 grid max-w-lg grid-cols-3 gap-3 animate-fade-up [animation-delay:460ms]">
                                <div class="border-l-2 border-neutral-300 pl-3 dark:border-neutral-700">
                                    <p class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Traceability</p>
                                    <p class="mt-0.5 text-[11px] leading-4 text-neutral-500 dark:text-neutral-400">Chain of custody</p>
                                </div>
                                <div class="border-l-2 border-neutral-300 pl-3 dark:border-neutral-700">
                                    <p class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">FEFO Control</p>
                                    <p class="mt-0.5 text-[11px] leading-4 text-neutral-500 dark:text-neutral-400">Batch &amp; expiry</p>
                                </div>
                                <div class="border-l-2 border-neutral-300 pl-3 dark:border-neutral-700">
                                    <p class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Audit-Ready</p>
                                    <p class="mt-0.5 text-[11px] leading-4 text-neutral-500 dark:text-neutral-400">Immutable ledger</p>
                                </div>
                            </div>
                        @endif
                    </section>

                    <div class="mx-auto min-w-0 w-full max-w-[min(29rem,100%)] animate-fade-in-scale overflow-hidden rounded-2xl border {{ $portalCardClass }} [animation-delay:200ms]">
                        <div class="{{ $isSuperAdminPortal ? 'p-7 sm:p-9' : 'p-6 sm:p-8' }}">
                            {{ $slot }}
                        </div>
                    </div>
                </main>

                <footer class="animate-fade-in flex flex-col gap-2 border-t py-5 text-xs [animation-delay:700ms] sm:flex-row sm:items-center sm:justify-between {{ $isThemeAwarePortal ? 'border-neutral-200/80 text-neutral-500 dark:border-neutral-800/80 dark:text-neutral-400' : 'border-white/10 text-neutral-500' }}">
                    <div>
                        &copy; {{ date('Y') }} HIMS
                        @if ($isSuperAdminPortal)
                            <span class="ml-2 text-neutral-500 dark:text-neutral-500">&middot; Privileged system access</span>
                        @elseif ($isAdminPortal)
                            <span class="ml-2 text-neutral-600">&middot; Administration access</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 {{ $isThemeAwarePortal ? 'text-neutral-600 dark:text-neutral-400' : 'text-neutral-400' }}">
                        <a href="{{ route('privacy.notice') }}" class="transition-colors {{ $isThemeAwarePortal ? 'hover:text-neutral-900 dark:hover:text-neutral-100' : 'hover:text-neutral-200' }}">Privacy Notice</a>
                        <a href="{{ route('terms') }}" class="transition-colors {{ $isThemeAwarePortal ? 'hover:text-neutral-900 dark:hover:text-neutral-100' : 'hover:text-neutral-200' }}">Terms of Use</a>
                    </div>
                </footer>
            </div>
        </div>

        @include('layouts.partials.decision-confirmation')
    </body>
</html>
