<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Supplier Portal' }} · HIMS</title>
    @include('layouts.partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $supplierUser = auth()->user();
    $supplierNavigation = [
        ['route' => 'supplier.dashboard', 'pattern' => 'supplier.dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
        ['route' => 'supplier.orders.index', 'pattern' => 'supplier.orders.*', 'label' => 'Purchase Orders', 'icon' => 'clipboard-document-list'],
        ['route' => 'supplier.rfqs.index', 'pattern' => 'supplier.rfqs.*', 'label' => 'RFQs', 'icon' => 'scale'],
        ['route' => 'supplier.discrepancies.index', 'pattern' => 'supplier.discrepancies.*', 'label' => 'Discrepancies', 'icon' => 'exclamation-triangle'],
        ['route' => 'supplier.catalog.index', 'pattern' => 'supplier.catalog.*', 'label' => 'Catalog', 'icon' => 'shopping-bag'],
        ['route' => 'supplier.compliance.index', 'pattern' => 'supplier.compliance.*', 'label' => 'Compliance', 'icon' => 'shield-check'],
        ['route' => 'supplier.invoices.index', 'pattern' => 'supplier.invoices.*', 'label' => 'Invoices', 'icon' => 'document-text'],
        ['route' => 'supplier.performance', 'pattern' => 'supplier.performance', 'label' => 'Performance', 'icon' => 'chart-bar'],
    ];
@endphp
<body class="supplier-portal min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100" data-supplier-portal>
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-neutral-900">Skip to main content</a>

    <header class="supplier-portal-header sticky top-0 z-50 border-b border-neutral-200/80 bg-white/95 dark:border-slate-800 dark:bg-[#08111c]/95" x-data="{ mobileMenuOpen: false }">
        <div class="grid min-h-[4.5rem] grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-4 sm:px-6 lg:px-8 2xl:grid-cols-[minmax(16rem,1fr)_auto_minmax(11rem,1fr)]">
            <a href="{{ route('supplier.dashboard') }}" class="flex min-w-0 shrink-0 items-center gap-3" aria-label="HIMS Supplier Portal home">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 ring-1 ring-primary-200 dark:bg-primary-950/80 dark:text-primary-300 dark:ring-primary-800/80">
                    <x-ui.icon name="cube" class="h-6 w-6" />
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-bold tracking-tight text-neutral-950 dark:text-white sm:text-base">HIMS Supplier Portal</span>
                    <span class="block max-w-48 truncate text-xs text-neutral-500 dark:text-slate-400">{{ $supplierUser->supplier->name }}</span>
                </span>
            </a>

            <nav aria-label="Supplier portal" class="hidden min-w-0 items-center justify-center gap-1 2xl:flex">
                @foreach ($supplierNavigation as $item)
                    @php($active = request()->routeIs($item['pattern']))
                    <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif class="inline-flex min-h-10 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition-colors {{ $active ? 'bg-primary-50 text-primary-700 ring-1 ring-primary-100 dark:bg-primary-950/80 dark:text-primary-200 dark:ring-primary-900' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-950 dark:text-slate-300 dark:hover:bg-slate-800/80 dark:hover:text-white' }}">
                        <x-ui.icon :name="$item['icon']" class="h-4 w-4" />
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="flex shrink-0 items-center justify-self-end gap-2">
                <x-ui.theme-toggle size="sm" class="border border-neutral-200 bg-white dark:border-slate-700 dark:bg-slate-900/70" />
                @if ($supplierUser->avatarUrl())
                    <x-ui.avatar :user="$supplierUser" size="sm" class="hidden ring-2 ring-primary-400/30 sm:flex" />
                @else
                    <span class="hidden h-9 w-9 items-center justify-center rounded-full bg-primary-400 text-white ring-2 ring-primary-300/30 sm:flex" aria-hidden="true">
                        <x-ui.icon name="user-circle" class="h-6 w-6" />
                    </span>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm font-semibold text-neutral-700 transition-colors hover:border-neutral-400 hover:bg-neutral-50 focus-visible:ring-primary-500 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800">
                        <span class="hidden sm:inline">Sign out</span>
                        <x-ui.icon name="arrow-right-on-rectangle" class="h-4 w-4" />
                    </button>
                </form>
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-300 text-neutral-700 hover:bg-neutral-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 2xl:hidden" x-on:click="mobileMenuOpen = ! mobileMenuOpen" x-bind:aria-expanded="mobileMenuOpen" aria-controls="supplier-mobile-navigation" aria-label="Toggle supplier navigation">
                    <x-ui.icon name="bars-3" class="h-5 w-5" x-show="! mobileMenuOpen" />
                    <x-ui.icon name="x-mark" class="h-5 w-5" x-cloak x-show="mobileMenuOpen" />
                </button>
            </div>
        </div>

        <nav id="supplier-mobile-navigation" aria-label="Supplier portal mobile" class="grid grid-cols-2 gap-1 border-t border-neutral-200/80 px-4 py-3 dark:border-slate-800 sm:grid-cols-4 2xl:hidden" x-cloak x-show="mobileMenuOpen" x-transition.opacity.duration.150ms>
            @foreach ($supplierNavigation as $item)
                @php($active = request()->routeIs($item['pattern']))
                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif class="flex min-h-11 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold {{ $active ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/80 dark:text-primary-200' : 'text-neutral-600 hover:bg-neutral-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    <x-ui.icon :name="$item['icon']" class="h-4 w-4 shrink-0" />
                    <span class="truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </header>

    <main id="main-content" class="supplier-portal-main px-4 py-6 sm:px-6 lg:px-8 lg:py-7">
        @if (session('success'))
            <x-ui.alert variant="success" :message="session('success')" class="mb-5" />
        @endif
        @if ($errors->any())
            <x-ui.alert variant="danger" title="Please correct the form" :message="$errors->first()" class="mb-5" />
        @endif
        {{ $slot }}
    </main>
</body>
</html>
