@php
    $cancelErrors = $errors->cancelActivation;
    $cancelUserId = is_numeric(old('cancel_user_id')) ? (int) old('cancel_user_id') : null;
    $cancelAction = $cancelUserId
        ? route(\App\Support\AuthenticationContext::administrationRoute('users.cancel-invitation'), $cancelUserId)
        : '';
@endphp

<div
    x-data="{
        actionUrl: @js($cancelAction),
        accountName: @js(old('cancel_account_name', '')),
        userId: @js($cancelUserId),
        reason: @js(old('cancellation_reason', '')),
        details: @js(old('cancellation_details', '')),
    }"
    x-on:open-cancel-activation.window="
        actionUrl = $event.detail.actionUrl;
        accountName = $event.detail.accountName;
        userId = $event.detail.userId;
        reason = '';
        details = '';
        $dispatch('open-modal', 'cancel-account-activation');
    "
    @if ($cancelErrors->any())
        x-init="$nextTick(() => $dispatch('open-modal', 'cancel-account-activation'))"
    @endif
>
    <x-ui.modal name="cancel-account-activation" title="Cancel Account Activation" maxWidth="lg">
        <form method="POST" x-bind:action="actionUrl" class="space-y-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="cancel_user_id" x-bind:value="userId">
            <input type="hidden" name="cancel_account_name" x-bind:value="accountName">

            <p class="text-sm leading-6 text-neutral-600 dark:text-neutral-300">
                Cancelling <strong class="font-semibold text-neutral-900 dark:text-neutral-100" x-text="accountName"></strong>'s activation will invalidate existing codes and links, prevent access to HIMS, and email the registered address.
            </p>

            <div class="space-y-1.5">
                <label for="cancellation_reason" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    Reason for Cancellation <span class="text-danger-600 dark:text-danger-400" aria-hidden="true">*</span>
                    <span class="sr-only">(required)</span>
                </label>
                <select
                    id="cancellation_reason"
                    name="cancellation_reason"
                    x-model="reason"
                    required
                    @class([
                        'block min-h-10 w-full rounded-md border bg-white pl-3 pr-10 text-sm text-neutral-900 shadow-sm focus:ring-2 focus:ring-offset-0 dark:bg-neutral-800 dark:text-neutral-100',
                        'border-danger-500 focus:border-danger-500 focus:ring-danger-500/30' => $cancelErrors->has('cancellation_reason'),
                        'border-neutral-300 focus:border-primary-500 focus:ring-primary-500/30 dark:border-neutral-700' => ! $cancelErrors->has('cancellation_reason'),
                    ])
                    @if ($cancelErrors->has('cancellation_reason')) aria-invalid="true" aria-describedby="cancellation_reason-error" @endif
                >
                    <option value="">Select a reason</option>
                    @foreach (\App\Enums\ActivationCancellationReason::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @if ($cancelErrors->has('cancellation_reason'))
                    <p id="cancellation_reason-error" class="text-xs font-medium text-danger-600 dark:text-danger-400">
                        {{ $cancelErrors->first('cancellation_reason') }}
                    </p>
                @endif
            </div>

            <div class="space-y-1.5">
                <label for="cancellation_details" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    Additional Details <span x-show="reason === 'other'" class="text-danger-600 dark:text-danger-400" aria-hidden="true">*</span>
                </label>
                <textarea
                    id="cancellation_details"
                    name="cancellation_details"
                    x-model="details"
                    rows="3"
                    maxlength="1000"
                    x-bind:required="reason === 'other'"
                    placeholder="Add context for the user or administrator"
                    @class([
                        'block min-h-10 w-full rounded-md border bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm placeholder:text-neutral-400 focus:ring-2 focus:ring-offset-0 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500',
                        'border-danger-500 focus:border-danger-500 focus:ring-danger-500/30' => $cancelErrors->has('cancellation_details'),
                        'border-neutral-300 focus:border-primary-500 focus:ring-primary-500/30 dark:border-neutral-700' => ! $cancelErrors->has('cancellation_details'),
                    ])
                    @if ($cancelErrors->has('cancellation_details')) aria-invalid="true" aria-describedby="cancellation_details-error" @else aria-describedby="cancellation_details-hint" @endif
                ></textarea>
                @if ($cancelErrors->has('cancellation_details'))
                    <p id="cancellation_details-error" class="text-xs font-medium text-danger-600 dark:text-danger-400">
                        {{ $cancelErrors->first('cancellation_details') }}
                    </p>
                @else
                    <p id="cancellation_details-hint" class="text-xs text-neutral-500 dark:text-neutral-400">
                        Required when Other is selected; optional for standard reasons.
                    </p>
                @endif
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800 sm:flex-row sm:justify-end">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'cancel-account-activation')">Keep Activation</x-ui.button>
                <x-ui.button type="submit" variant="danger" data-loading-text="Cancelling activation...">Cancel Activation</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
