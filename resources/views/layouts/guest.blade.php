<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-800 antialiased bg-[radial-gradient(circle_at_top,_#eaf8f3_0%,_#f4fafd_55%,_#f8fbfd_100%)]">
        <div class="min-h-screen flex flex-col sm:justify-center items-center px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="/" class="inline-flex items-center gap-3 rounded-full border border-emerald-200/70 bg-white/80 px-4 py-2 shadow-sm backdrop-blur">
                    <x-application-logo class="h-10 w-10 fill-current text-emerald-600" />
                    <span class="text-sm font-semibold uppercase tracking-[0.25em] text-emerald-700">HIMS</span>
                </a>
            </div>

            <div class="w-full sm:max-w-md overflow-hidden rounded-2xl border border-emerald-100 bg-white p-8 shadow-[0_10px_30px_rgba(2,132,99,0.12)]">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
