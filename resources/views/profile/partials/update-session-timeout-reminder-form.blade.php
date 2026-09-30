<section class="py-4" x-data="{ original: @js((bool) $user->session_timeout_reminder_enabled) }">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Session Timeout Reminder') }}</h3>
            <p class="mt-0.5 text-xs leading-5 text-neutral-600 dark:text-neutral-300">{{ __('Warn before an inactive session expires.') }}</p>
        </div>
        <x-ui.switch
            :checked="$user->session_timeout_reminder_enabled"
            label="Configure Session Timeout Reminder"
            x-bind:aria-checked="original"
            x-on:click="$dispatch('open-modal', 'configure-session-reminder')"
        />
    </div>

    <div x-data @if ($errors->has('session_timeout_reminder_enabled')) x-init="$nextTick(() => $dispatch('open-modal', 'configure-session-reminder'))" @endif>
    <x-ui.modal name="configure-session-reminder" :title="__('Session Timeout Reminder')" maxWidth="md">
        <form method="post" action="{{ route('profile.session-timeout-reminder.update') }}">
            @csrf
            @method('patch')
            <input type="hidden" name="session_timeout_reminder_enabled" value="{{ $user->session_timeout_reminder_enabled ? '0' : '1' }}">

            <p class="text-sm leading-6 text-neutral-600 dark:text-neutral-300">
                {{ $user->session_timeout_reminder_enabled
                    ? __('Turn off the countdown dialog and alert sound before the session expires. Secure automatic logout will remain active.')
                    : __('Turn on the countdown dialog and alert sound before the session expires.') }}
            </p>

            <x-input-error :messages="$errors->get('session_timeout_reminder_enabled')" class="mt-1" />

            <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'configure-session-reminder')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" data-loading-text="Saving reminder setting...">
                    {{ $user->session_timeout_reminder_enabled ? __('Turn off reminder') : __('Turn on reminder') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    </div>
</section>
