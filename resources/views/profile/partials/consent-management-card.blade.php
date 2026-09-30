<x-ui.card
    :title="__('Consent & Privacy Preferences')"
    :subtitle="__('Manage your institutional privacy acknowledgments, optional processing permissions, and consent history.')"
>
    <div class="space-y-4">
        {{-- 1. Mandatory Privacy Policy --}}
        <div class="rounded-lg border border-neutral-200 dark:border-neutral-700 p-3.5 bg-neutral-50/50 dark:bg-neutral-800/40 space-y-2">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ __('System Privacy Policy & Terms of Use') }}
                        </span>
                        <span class="text-[11px] font-mono px-1.5 py-0.2 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300">
                            {{ config('privacy.policy_version', 'v1.0') }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-300">
                        {{ __('Mandatory statutory governance governing healthcare account access and inventory chain-of-custody under RA 10173.') }}
                    </p>
                </div>
                @if (!($consentSummary['policy']['is_current'] ?? false))
                    <div class="shrink-0 flex items-center gap-2">
                        <x-ui.badge status="action_required" dot>
                            {{ __('Renewal Required') }}
                        </x-ui.badge>
                        <a href="{{ route('consent.privacy-policy') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700 underline">
                            {{ __('Review & Acknowledge') }}
                        </a>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-between pt-1 text-[11px] text-neutral-500 border-t border-neutral-200/60 dark:border-neutral-700/60">
                <span>
                    @if ($consentSummary['policy']['consented_at'] ?? false)
                        {{ __('Consented on :date', ['date' => $consentSummary['policy']['consented_at']->format('M d, Y H:i')]) }}
                    @else
                        {{ __('No recorded acceptance for current version.') }}
                    @endif
                </span>
                <a href="{{ route('privacy.notice') }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:text-primary-700 underline font-medium inline-flex items-center gap-1">
                    <span>{{ __('View Current Policy') }}</span>
                    <x-ui.icon name="arrow-top-right-on-square" class="h-3 w-3" />
                </a>
            </div>
        </div>

        {{-- 2. Optional Consent: Geolocation Audit Tracking --}}
        <div class="rounded-lg border border-neutral-200 dark:border-neutral-700 p-3.5 bg-neutral-50/50 dark:bg-neutral-800/40 space-y-3">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ __('High-Accuracy Audit Geolocation') }}
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-400">
                            {{ __('Optional') }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-300">
                        {{ __('Allows HIMS to record device-reported coordinates during signed-in sessions to verify authorized facility access in the security audit trail.') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pt-2 border-t border-neutral-200/60 dark:border-neutral-700/60">
                <p class="text-[11px] text-neutral-500">
                    @if ($consentSummary['optional_location']['is_active'] ?? false)
                        {{ __('Active since :date. You may withdraw this permission at any time.', ['date' => $consentSummary['optional_location']['consented_at']?->format('M d, Y H:i') ?? 'N/A']) }}
                    @elseif ($consentSummary['optional_location']['withdrawn_at'] ?? false)
                        {{ __('Withdrawn on :date. Browser coordinates are not collected.', ['date' => $consentSummary['optional_location']['withdrawn_at']?->format('M d, Y H:i')]) }}
                    @else
                        {{ __('Declined/Not granted. Only coarse IP-based country/region is derived.') }}
                    @endif
                </p>

                @if ($consentSummary['optional_location']['is_active'] ?? false)
                    <form method="POST" action="{{ route('profile.consent.optional') }}" class="shrink-0">
                        @csrf
                        <input type="hidden" name="consent_type" value="audit_browser_location">
                        <input type="hidden" name="status" value="withdrawn">
                        <x-ui.button type="submit" variant="danger" size="sm">
                            {{ __('Withdraw Permission') }}
                        </x-ui.button>
                    </form>
                @else
                    <form method="POST" action="{{ route('profile.consent.optional') }}" class="shrink-0">
                        @csrf
                        <input type="hidden" name="consent_type" value="audit_browser_location">
                        <input type="hidden" name="status" value="consented">
                        <x-ui.button type="submit" variant="secondary" size="sm">
                            {{ __('Grant Permission') }}
                        </x-ui.button>
                    </form>
                @endif
            </div>
        </div>

        {{-- 3. Consent History Table --}}
        @if (!empty($consentSummary['history']) && count($consentSummary['history']) > 0)
            <details class="rounded-lg border border-neutral-200 dark:border-neutral-700 text-xs">
                <summary class="cursor-pointer px-3 py-2.5 font-semibold text-neutral-800 dark:text-neutral-200 hover:text-neutral-900 dark:hover:text-white focus:outline-none">
                    {{ __('View Consent History & Audit Evidence (:count records)', ['count' => count($consentSummary['history'])]) }}
                </summary>
                <div class="border-t border-neutral-200 dark:border-neutral-700 p-3 overflow-x-auto">
                    <table class="w-full text-left text-[11px]">
                        <thead>
                            <tr class="border-b border-neutral-200 dark:border-neutral-700 text-neutral-500 font-medium">
                                <th class="pb-1.5">{{ __('Purpose') }}</th>
                                <th class="pb-1.5">{{ __('Version') }}</th>
                                <th class="pb-1.5">{{ __('Status') }}</th>
                                <th class="pb-1.5">{{ __('Recorded At') }}</th>
                                <th class="pb-1.5">{{ __('Context / Source') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @foreach ($consentSummary['history'] as $item)
                                <tr>
                                    <td class="py-1.5 font-medium text-neutral-800 dark:text-neutral-200">
                                        {{ $item->consent_type === 'privacy_policy' ? 'Privacy Policy & Terms' : 'High-Accuracy Geolocation' }}
                                    </td>
                                    <td class="py-1.5 font-mono text-neutral-600 dark:text-neutral-400">
                                        {{ $item->policy_version }}
                                    </td>
                                    <td class="py-1.5">
                                        @if ($item->status === 'consented')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                {{ __('Consented') }}
                                            </span>
                                        @elseif ($item->status === 'withdrawn')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                {{ __('Withdrawn') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300">
                                                {{ ucfirst($item->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-1.5 text-neutral-600 dark:text-neutral-400">
                                        {{ $item->consented_at?->format('M d, Y H:i') ?? $item->created_at->format('M d, Y H:i') }}
                                        @if ($item->withdrawn_at)
                                            <span class="text-neutral-400 text-[10px] block">
                                                {{ __('Withdrawn: :time', ['time' => $item->withdrawn_at->format('M d, Y H:i')]) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-1.5 font-mono text-neutral-500">
                                        {{ $item->source ?? 'system' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </div>
</x-ui.card>
