<div
    x-data="{
        requestType: @js(old('request_type', 'access')),
        descriptions: {
            access: 'Request an export copy of your account profile, role assignments, and personal activity metadata held in HIMS under RA 10173 Sec. 16(c).',
            correction: 'Request rectification of inaccurate, outdated, or incomplete employee profile data under RA 10173 Sec. 16(d).',
            erasure: 'Request deletion or anonymization of eligible personal information. Required security, audit, inventory, warehouse, and transaction history will remain for integrity and accountability.',
            objection: 'Object to the processing of personal data for non-mandatory secondary purposes under RA 10173 Sec. 16(b).'
        }
    }"
    @if ($errors->hasAny(['request_type', 'details', 'current_password', 'confirm_deletion']))
        x-init="$nextTick(() => { $dispatch('open-modal', 'submit-privacy-request') })"
    @endif
>
    <x-ui.modal name="submit-privacy-request" :title="__('Exercise Data Subject Rights (RA 10173)')" maxWidth="lg">
        @if ($errors->hasAny(['request_type', 'details', 'current_password', 'confirm_deletion']))
            <x-ui.alert variant="danger" title="Submission Error" dismissible class="mb-4">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <div class="space-y-4">
            <x-ui.alert variant="info" title="Republic Act No. 10173 — Data Privacy Act">
                As a hospital employee or authorized system user, you have statutory rights to access, rectify, or request restriction of your personal data processed within the DJNRMHS Hospital Information Management System.
            </x-ui.alert>

            <form method="POST" action="{{ route('privacy.requests.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="request_type" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                        {{ __('Request Category') }} <span class="text-danger-600 dark:text-danger-400">*</span>
                    </label>
                    <select
                        id="request_type"
                        name="request_type"
                        x-model="requestType"
                        class="mt-1.5 block w-full rounded-lg border-neutral-300 bg-white pl-3 pr-10 text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                        required
                    >
                        <option value="access">Right to Access &amp; Data Portability (Sec. 16c)</option>
                        <option value="correction">Right to Rectification / Correction (Sec. 16d)</option>
                        <option value="erasure">Right to Erasure / Account Deactivation (Sec. 16e)</option>
                        <option value="objection">Right to Object / Restrict Processing (Sec. 16b)</option>
                    </select>

                    <p class="mt-2 rounded border border-neutral-200 bg-neutral-50 p-2.5 text-xs leading-normal text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-300" x-text="descriptions[requestType]"></p>
                </div>

                <div x-show="requestType === 'erasure'" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                    This requests deletion of eligible personal information, not destruction of required HIMS records. Completion disables account access, revokes active sessions and trusted browsers, and may be irreversible.
                </div>

                <div>
                    <label for="details" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                        <span x-text="requestType === 'erasure' ? 'Optional reason' : 'Specific Details or Request Ground'"></span>
                        <span x-show="requestType !== 'erasure'" class="text-danger-600 dark:text-danger-400">*</span>
                    </label>
                    <textarea
                        id="details"
                        name="details"
                        rows="4"
                        class="mt-1.5 block w-full rounded-lg border-neutral-300 bg-white text-sm text-neutral-900 shadow-sm placeholder:text-neutral-500 focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder:text-neutral-400"
                        placeholder="Describe your request without including unnecessary personal information."
                        x-bind:required="requestType !== 'erasure'"
                    >{{ old('details') }}</textarea>
                    <p class="mt-1 text-xs leading-normal text-neutral-600 dark:text-neutral-300" x-text="requestType === 'erasure' ? 'A reason is optional. Do not include unnecessary personal information.' : 'Minimum 10 characters. Your request will be securely logged and reviewed by the Data Protection Officer.'"></p>
                </div>

                <div x-show="requestType === 'erasure'" x-cloak class="space-y-3">
                    <div>
                        <label for="deletion_current_password" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Current Password <span class="text-danger-600 dark:text-danger-400">*</span></label>
                        <input id="deletion_current_password" name="current_password" type="text" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-form-type="other" style="-webkit-text-security: disc; text-security: disc;" x-bind:required="requestType === 'erasure'" class="mt-1.5 block w-full rounded-lg border-neutral-300 bg-white text-sm text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
                        <x-input-error :messages="$errors->get('current_password')" class="mt-1" />
                    </div>

                    <label class="flex items-start gap-2.5 text-xs leading-relaxed text-neutral-700 dark:text-neutral-300">
                        <input name="confirm_deletion" type="checkbox" value="1" x-bind:required="requestType === 'erasure'" class="mt-0.5 rounded border-neutral-300 text-danger-600 focus:ring-danger-500">
                        <span>I confirm that I am requesting deletion or anonymization of eligible personal information, understand that required historical records will remain, and understand that account access will end once processing is completed.</span>
                    </label>
                    <x-input-error :messages="$errors->get('confirm_deletion')" />
                </div>

                <div class="flex flex-col gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-end">
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        x-on:click="$dispatch('close-modal', 'submit-privacy-request')"
                        class="w-full sm:w-auto"
                    >
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        class="w-full sm:w-auto"
                        data-loading-text="Submitting request..."
                    >
                        {{ __('Submit Request to DPO') }}
                    </x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>
</div>
