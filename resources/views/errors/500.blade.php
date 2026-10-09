<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <title>500 | System Error Intercepted &middot; HIMS</title>

        @include('layouts.partials.font-loader')
        @vite('resources/css/app.css')
    </head>
    <body class="h-full bg-neutral-50 font-sans text-neutral-800 antialiased">
        <main class="flex min-h-full items-center justify-center px-4 py-10 sm:px-6">
            <section class="w-full max-w-lg" aria-labelledby="server-error-title">
                <div class="mb-6 flex items-center justify-center gap-3">
                    <img
                        src="{{ asset('img/hims-logo.png') }}"
                        alt=""
                        class="h-11 w-11 rounded-lg border border-neutral-200 bg-white object-cover shadow-sm"
                    >
                    <div>
                        <p class="text-base font-semibold leading-5 tracking-tight text-neutral-900">HIMS</p>
                        <p class="text-xs text-neutral-500">Hospital Information Management System</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="h-1.5 bg-rose-600" aria-hidden="true"></div>

                    <div class="px-6 py-9 text-center sm:px-10 sm:py-11">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-rose-100 bg-rose-50 text-rose-600">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>

                        <p class="mt-6 text-5xl font-bold tracking-tight text-rose-600" aria-hidden="true">500</p>
                        <h1 id="server-error-title" class="mt-2 text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">
                            <span class="sr-only">Error 500: </span>System Error Intercepted
                        </h1>

                        <p class="mx-auto mt-4 max-w-sm text-sm leading-6 text-neutral-600 sm:text-base sm:leading-7">
                            An unexpected issue occurred while processing your operation. All active database transactions were automatically rolled back to safeguard inventory and clinical records.
                        </p>

                        @php
                            $refId = $errorId ?? session('error_id') ?? request()->get('error_id');
                        @endphp

                        @if ($refId)
                            <div class="mt-5 inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50 px-3.5 py-2 font-mono text-xs text-neutral-700 shadow-sm">
                                <span class="font-semibold text-neutral-500 uppercase tracking-wider text-[10px]">Incident Reference:</span>
                                <span class="font-bold text-rose-700 select-all">{{ $refId }}</span>
                            </div>
                        @endif

                        <div class="mt-7 flex flex-col sm:flex-row items-center justify-center gap-3">
                            <a
                                href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}"
                                class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl bg-neutral-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800"
                            >
                                Return to Previous Page
                            </a>
                            <a
                                href="{{ request()->fullUrl() }}"
                                class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50"
                            >
                                Try Again
                            </a>
                        </div>

                    </div>
                </div>

                <p class="mt-5 text-center text-xs leading-5 text-neutral-500">
                    No partial data was committed. Technical details have been logged securely for hospital IT administration.
                </p>
            </section>
        </main>
    </body>
</html>
