<x-guest-layout
    :title="$panel->label().' Cancel Sign-In Request'"
    portal="{{ $panel->value }}"
>
    <div class="space-y-6 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-700 ring-8 ring-amber-50/50 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-950/30">
            <x-ui.icon name="exclamation-triangle" class="h-7 w-7" />
        </div>

        <div>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                Cancel this sign-in request?
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">
                The pending request from {{ $approvalRequest->device_name ?: 'this browser' }} will be cancelled. You can sign in again afterward.
            </p>
        </div>

        <div class="space-y-3">
            <form method="POST" action="{{ $cancelUrl }}">
                @csrf
                <button
                    type="submit"
                    data-loading-text="Cancelling..."
                    class="inline-flex w-full items-center justify-center rounded-lg bg-danger-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-danger-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900"
                >
                    Cancel Request & Return to Sign-In
                </button>
            </form>

            <a
                href="{{ $waitingUrl }}"
                class="inline-flex w-full items-center justify-center rounded-lg border border-neutral-300 bg-white px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800 dark:focus-visible:ring-offset-neutral-900"
            >
                Keep Waiting
            </a>
        </div>
    </div>
</x-guest-layout>
