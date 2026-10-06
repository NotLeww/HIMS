<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Supplier Portal' }} · HIMS</title>
    @include('layouts.partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
<a href="#main-content" class="sr-only focus:not-sr-only">Skip to main content</a>
<header class="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
    <div class="mx-auto flex max-w-none flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <div class="min-w-0"><p class="text-sm font-semibold text-primary-700 dark:text-primary-300">HIMS Supplier Portal</p><p class="truncate text-xs text-neutral-500">{{ auth()->user()->supplier->name }}</p></div>
        <nav aria-label="Supplier portal" class="flex flex-wrap gap-1 text-sm">
            @foreach ([['supplier.dashboard','Dashboard'],['supplier.orders.index','Purchase Orders'],['supplier.rfqs.index','RFQs'],['supplier.discrepancies.index','Discrepancies'],['supplier.catalog.index','Catalog'],['supplier.compliance.index','Compliance'],['supplier.invoices.index','Invoices'],['supplier.performance','Performance']] as [$route,$label])
                <a href="{{ route($route) }}" class="rounded-md px-3 py-2 font-medium {{ request()->routeIs($route) || request()->routeIs(str_replace('.index','.*',$route)) ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-200' : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-md border border-neutral-300 px-3 py-2 text-sm font-semibold dark:border-neutral-700">Sign out</button></form>
    </div>
</header>
<main id="main-content" class="mx-auto max-w-none px-4 py-6 sm:px-6 lg:px-8">
    @if(session('success'))<x-ui.alert variant="success" :message="session('success')" />@endif
    @if($errors->any())<x-ui.alert variant="danger" title="Please correct the form" :message="$errors->first()" />@endif
    {{ $slot }}
</main>
</body></html>
