<x-guest-layout title="Sign-in decision">
    <div class="space-y-5 text-center">
        <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
            {{ $success ? 'Decision Confirmed' : 'Request Unavailable' }}
        </h1>
        <p class="text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">{{ $message }}</p>
        <p class="text-xs leading-relaxed text-neutral-500 dark:text-neutral-400">You may close this page. The requesting browser will update automatically.</p>
    </div>
</x-guest-layout>
