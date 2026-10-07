<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <title>{{ $title ?? 'Supplier Portal' }} &middot; HIMS</title>

    @include('layouts.partials.theme-script')
    @include('layouts.partials.navigation-loading-state')
    @include('layouts.partials.font-loader')
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --hims-sidebar-day-image: url('{{ asset('img/hims-supplier-sidebar-day.png') }}');
            --hims-sidebar-night-image: url('{{ asset('img/hims-supplier-sidebar-night.png') }}');
            --hims-header-image: url('{{ asset('img/hims-supplier-dashboard-hero-light.png') }}');
        }

        .dark {
            --hims-header-image: url('{{ asset('img/hims-supplier-dashboard-hero.png') }}');
        }
    </style>
</head>
<body class="supplier-portal h-full bg-neutral-50 font-sans text-neutral-800 antialiased dark:bg-neutral-950 dark:text-neutral-100" data-supplier-portal>
    <a href="#main-content" class="sr-only fixed left-4 top-4 z-[100] rounded-md bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-lg focus:not-sr-only focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-neutral-900 dark:text-primary-300">
        Skip to main content
    </a>

    @include('layouts.partials.loading-overlay')

    <div
        x-data="{
            sidebarOpen: window.innerWidth >= 1024,
            isMobile: window.innerWidth < 1024,
            init() {
                this.$el.setAttribute('data-ready', '');
                window.addEventListener('resize', () => {
                    const mobile = window.innerWidth < 1024;
                    if (mobile !== this.isMobile) {
                        this.isMobile = mobile;
                        this.sidebarOpen = !mobile;
                    }
                });
            }
        }"
        x-on:keydown.window.escape="if (isMobile) sidebarOpen = false"
        class="hims-app-shell min-h-full overflow-x-clip"
    >
        @include('layouts.partials.sidebar')

        <div
            x-show="sidebarOpen && isMobile"
            x-cloak
            x-transition.opacity.duration.200ms
            x-on:click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-neutral-950/60 backdrop-blur-xs lg:hidden"
            aria-hidden="true"
        ></div>

        <div
            class="w-full min-w-0 max-w-full transition-[padding] duration-200 lg:pl-64"
            :class="{ 'lg:pl-64': sidebarOpen, 'lg:pl-0': !sidebarOpen }"
        >
            @include('layouts.partials.topbar')

            <main id="main-content" tabindex="-1" class="hims-app-content overflow-x-clip px-4 pb-6 pt-4 sm:px-6 lg:px-8 lg:pb-8">
                <div class="mx-auto w-full min-w-0 max-w-none space-y-6">
                    @if ($errors->any())
                        <x-ui.alert variant="danger" title="Please correct the form" :message="$errors->first()" />
                    @endif

                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @include('layouts.partials.toast-notifications')
    @include('layouts.partials.decision-confirmation')
</body>
</html>
