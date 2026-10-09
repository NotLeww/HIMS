<section class="py-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Browsers & Sessions') }}</h3>
            <p class="mt-0.5 text-xs leading-5 text-neutral-600 dark:text-neutral-300">
                {{ __('Manage trusted browser profiles and view your current active session.') }}
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <x-ui.badge status="active" dot>{{ $trustedDevices->count() }} {{ str('Browser')->plural($trustedDevices->count()) }}</x-ui.badge>
            <x-ui.button type="button" size="sm" variant="secondary" x-on:click="$dispatch('open-modal', 'manage-devices-modal')">
                {{ __('Manage Browsers') }}
            </x-ui.button>
        </div>
    </div>

    <x-ui.modal name="manage-devices-modal" :title="__('Trusted Browsers & Active Session')" maxWidth="lg">
        <div class="space-y-5">
            {{-- Active Session Card --}}
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                    {{ __('Current Active Session') }}
                </h4>
                <div class="mt-2 rounded-lg border border-primary-200 bg-primary-50/50 p-3.5 dark:border-primary-900/60 dark:bg-primary-950/30">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/60 dark:text-primary-300">
                                <x-ui.icon name="computer-desktop" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                    {{ $activeSession?->device_name ?: 'Current Browser' }}
                                </p>
                                <p class="text-xs text-neutral-600 dark:text-neutral-400">
                                    {{ $activeSession?->browser }} on {{ $activeSession?->platform }} &bull; IP: {{ $activeSession?->ip_address ?: 'Unknown' }}
                                </p>
                                <p class="mt-1 text-[11px] text-neutral-500">
                                    {{ __('Active since') }}: {{ $activeSession?->created_at?->timezone(config('app.timezone', 'UTC'))->format('M d, Y h:i A') ?? 'Now' }}
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-[11px] font-medium text-success-700 border border-success-200 dark:bg-success-950/50 dark:text-success-300 dark:border-success-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                            {{ __('This Browser') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Trusted Browsers List --}}
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                    {{ __('Trusted Browsers') }} ({{ $trustedDevices->count() }})
                </h4>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    {{ __('These browser profiles can take over your active session without waiting for approval.') }}
                </p>

                @if ($trustedDevices->isEmpty())
                    <div class="mt-3 rounded-lg border border-dashed border-neutral-300 p-6 text-center dark:border-neutral-700">
                        <x-ui.empty-artwork category="users" size="sm" />
                        <p class="mt-2 text-xs font-semibold text-neutral-700 dark:text-neutral-300">{{ __('No trusted browsers registered yet') }}</p>
                    </div>
                @else
                    <ul class="mt-3 divide-y divide-neutral-200 rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
                        @foreach ($trustedDevices as $device)
                            <li class="flex items-center justify-between p-3.5 text-xs">
                                <div class="min-w-0 pr-3">
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium text-neutral-900 dark:text-neutral-100">
                                            {{ $device->display_name }}
                                        </p>
                                        @if ($activeSession?->trusted_device_id === $device->id)
                                            <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                                {{ __('Active') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 text-neutral-500 dark:text-neutral-400">
                                        {{ $device->user_agent_summary ?: __('Browser details unavailable') }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-neutral-400">
                                        {{ __('Last used') }}: {{ $device->last_used_at?->diffForHumans() ?? 'Never' }} &bull;
                                        {{ __('Expires') }}: {{ $device->expires_at?->diffForHumans() ?? 'Unknown' }}
                                    </p>
                                </div>
                                <div>
                                    <form
                                        method="POST"
                                        action="{{ route('profile.trusted-devices.destroy', $device) }}"
                                        data-confirm-title="Revoke trusted browser"
                                        data-confirm-message="Revoke trust for this browser profile? It will be required to verify again on its next sign-in."
                                        data-confirm-label="Revoke Browser"
                                        data-confirm-destructive
                                    >
                                        @csrf
                                        <button
                                            type="submit"
                                            data-loading-text="Revoking..."
                                            class="rounded px-2.5 py-1 text-xs font-medium text-danger-600 hover:bg-danger-50 hover:text-danger-700 dark:text-danger-400 dark:hover:bg-danger-950/40 transition"
                                        >
                                            {{ __('Revoke') }}
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="flex items-center justify-end border-t border-neutral-200 pt-4 dark:border-neutral-800">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'manage-devices-modal')">
                    {{ __('Close') }}
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</section>
