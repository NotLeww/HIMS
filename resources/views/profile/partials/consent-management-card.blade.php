<x-ui.card
    :title="__('Consent & Privacy Preferences')"
    :subtitle="__('Review your policy consent and optional location access.')"
>
    <div class="divide-y divide-neutral-200 dark:divide-neutral-800">
        <section class="pb-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('System Privacy Policy & Terms of Use') }}
                    </h3>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        {{ __('Version :version', ['version' => config('privacy.policy_version', 'v1.0')]) }}
                        <span aria-hidden="true">·</span>
                        @if ($consentSummary['policy']['consented_at'] ?? false)
                            {{ __('Accepted :date', ['date' => $consentSummary['policy']['consented_at']->format('M d, Y H:i')]) }}
                        @else
                            {{ __('Acceptance not recorded') }}
                        @endif
                    </p>
                </div>

                @if (!($consentSummary['policy']['is_current'] ?? false))
                    <a href="{{ route('consent.privacy-policy') }}" class="shrink-0 text-xs font-semibold text-primary-600 underline underline-offset-2 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                        {{ __('Review and accept') }}
                    </a>
                @else
                    <a href="{{ route('privacy.notice') }}" target="_blank" rel="noopener noreferrer" class="shrink-0 text-xs font-medium text-primary-600 underline underline-offset-2 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                        {{ __('View Current Policy') }}
                    </a>
                @endif
            </div>
        </section>

        <section class="py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('High-Accuracy Audit Geolocation') }}
                        <span class="font-normal text-neutral-500 dark:text-neutral-400">({{ __('Optional') }})</span>
                    </h3>
                    <p class="mt-1 max-w-2xl text-xs text-neutral-600 dark:text-neutral-300">
                        {{ __('Allow HIMS to record device coordinates during signed-in sessions for security audits.') }}
                    </p>
                    <p class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                        @if ($consentSummary['optional_location']['is_active'] ?? false)
                            {{ __('On since :date', ['date' => $consentSummary['optional_location']['consented_at']?->format('M d, Y H:i') ?? 'N/A']) }}
                        @else
                            {{ __('Off. Browser coordinates are not collected; approximate location may still be derived from your IP.') }}
                        @endif
                    </p>
                </div>

                @if ($consentSummary['optional_location']['is_active'] ?? false)
                    <form
                        method="POST"
                        action="{{ route('profile.consent.optional') }}"
                        class="shrink-0"
                        data-confirm-title="{{ __('Turn off audit geolocation?') }}"
                        data-confirm-message="{{ __('HIMS will stop collecting browser coordinates. Approximate location may still be derived from your IP.') }}"
                        data-confirm-label="{{ __('Turn off') }}"
                    >
                        @csrf
                        <input type="hidden" name="consent_type" value="audit_browser_location">
                        <input type="hidden" name="status" value="withdrawn">
                        <x-ui.button type="submit" variant="secondary" size="sm" data-loading-text="{{ __('Turning off...') }}">
                            {{ __('Turn off') }}
                        </x-ui.button>
                    </form>
                @else
                    <form
                        method="POST"
                        action="{{ route('profile.consent.optional') }}"
                        class="shrink-0"
                        data-confirm-title="{{ __('Turn on audit geolocation?') }}"
                        data-confirm-message="{{ __('Allow HIMS to collect browser coordinates during signed-in sessions for security audits?') }}"
                        data-confirm-label="{{ __('Turn on') }}"
                        data-confirm-variant="info"
                    >
                        @csrf
                        <input type="hidden" name="consent_type" value="audit_browser_location">
                        <input type="hidden" name="status" value="consented">
                        <x-ui.button type="submit" variant="secondary" size="sm" data-loading-text="{{ __('Turning on...') }}">
                            {{ __('Turn on') }}
                        </x-ui.button>
                    </form>
                @endif
            </div>
        </section>

        @if (!empty($consentSummary['history']) && count($consentSummary['history']) > 0)
            <div class="pt-4">
                <x-ui.button type="button" variant="secondary" size="sm" x-data x-on:click="$dispatch('open-modal', 'consent-history-modal')">
                    {{ __('View consent history (:count)', ['count' => count($consentSummary['history'])]) }}
                </x-ui.button>
            </div>
        @endif
    </div>
</x-ui.card>

@if (!empty($consentSummary['history']) && count($consentSummary['history']) > 0)
    <x-ui.modal name="consent-history-modal" :title="__('Consent History')" maxWidth="4xl">
        <p class="mb-4 text-xs text-neutral-500 dark:text-neutral-400">
            {{ __('A record of your privacy policy and optional location consent changes.') }}
        </p>

        <x-ui.table :stickyHeader="false">
            <x-ui.table.head :sticky="false">
                <x-ui.table.th>{{ __('Purpose') }}</x-ui.table.th>
                <x-ui.table.th>{{ __('Version') }}</x-ui.table.th>
                <x-ui.table.th>{{ __('Status') }}</x-ui.table.th>
                <x-ui.table.th>{{ __('Recorded At') }}</x-ui.table.th>
                <x-ui.table.th>{{ __('Source') }}</x-ui.table.th>
            </x-ui.table.head>
            <tbody>
                @foreach ($consentSummary['history'] as $item)
                    <x-ui.table.row>
                        <x-ui.table.td class="font-medium">
                            {{ $item->consent_type === 'privacy_policy' ? __('Privacy Policy & Terms') : __('High-Accuracy Geolocation') }}
                        </x-ui.table.td>
                        <x-ui.table.td class="font-mono text-xs">
                            {{ $item->policy_version }}
                        </x-ui.table.td>
                        <x-ui.table.td>
                            <x-ui.badge :variant="$item->status === 'consented' ? 'success' : ($item->status === 'withdrawn' ? 'warning' : 'neutral')">
                                {{ __(ucfirst($item->status)) }}
                            </x-ui.badge>
                        </x-ui.table.td>
                        <x-ui.table.td class="whitespace-nowrap text-xs tabular-nums">
                            {{ $item->consented_at?->format('M d, Y H:i') ?? $item->created_at->format('M d, Y H:i') }}
                            @if ($item->withdrawn_at)
                                <span class="mt-0.5 block text-[11px] text-neutral-500 dark:text-neutral-400">
                                    {{ __('Withdrawn: :time', ['time' => $item->withdrawn_at->format('M d, Y H:i')]) }}
                                </span>
                            @endif
                        </x-ui.table.td>
                        <x-ui.table.td class="font-mono text-xs" muted>
                            {{ $item->source ?? 'system' }}
                        </x-ui.table.td>
                    </x-ui.table.row>
                @endforeach
            </tbody>
        </x-ui.table>

        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'consent-history-modal')">
                {{ __('Close') }}
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
@endif
