{{--
    Shared by the create and edit screens. The only differences are whether a
    password is required and whether the status field is offered, so the two
    pages pass $user and everything else follows from it. Included rather than
    made a component: it belongs to this module, not to the design system.

    Alpine mirrors the role list so picking a role immediately shows what that
    role can do — assigning access blind is how people end up over-permissioned.
--}}
@php
    $user = $user ?? null;
    $isEdit = $user !== null;
    $nameComponents = $user?->nameComponents() ?? [
        'surname' => null,
        'first_name' => null,
        'middle_name' => null,
    ];
    $roleDescriptions = collect($roles)->mapWithKeys(function ($role) {
        $grouped = [];
        foreach ($role->permissions() as $permission) {
            $module = $permission->module();
            if (! isset($grouped[$module])) {
                $grouped[$module] = [
                    'module' => $module,
                    'items' => [],
                ];
            }
            $grouped[$module]['items'][] = [
                'label' => $permission->label(),
                'description' => $permission->description(),
            ];
        }

        return [
            $role->value => [
                'label' => $role->label(),
                'description' => $role->description(),
                'total_count' => count($role->permissions()),
                'groups' => array_values($grouped),
                'permissions' => collect($role->permissions())->map->label()->values(),
            ],
        ];
    });
@endphp

<div x-data="{
    role: '{{ old('role', $user?->role?->value ?? (collect($roles)->first()?->value ?? \App\Enums\UserRole::Viewer->value)) }}',
    roles: {{ \Illuminate\Support\Js::from($roleDescriptions) }},
    password: '',
    passwordConfirmation: '',
    permissionSearch: '',
    sanitizeNamePart(event) {
        event.target.value = event.target.value
            .replace(/[^\p{L} ]/gu, '')
            .replace(/ {2,}/g, ' ')
            .replace(/^ +/, '');
    },
    get detail() { return this.roles[this.role] ?? null },
    visibleGroups() {
        if (!this.detail?.groups) return [];
        const query = this.permissionSearch.trim().toLowerCase();
        if (!query) return this.detail.groups;
        return this.detail.groups.map(group => {
            const filteredItems = group.items.filter(item =>
                item.label.toLowerCase().includes(query) ||
                item.description.toLowerCase().includes(query)
            );
            return filteredItems.length > 0 ? { ...group, items: filteredItems } : null;
        }).filter(Boolean);
    },
}">
    @if (! $isEdit)
        <div class="mb-4 flex justify-end">
            <x-ui.button
                type="button"
                variant="secondary"
                size="sm"
                icon="shield-check"
                x-on:click="$dispatch('open-modal', 'form-role-permissions-modal')">
                View Role Permissions
            </x-ui.button>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <div class="grid gap-4 sm:grid-cols-2 md:col-span-2 lg:grid-cols-3">
            <x-ui.field
                name="surname"
                label="Surname"
                :value="$nameComponents['surname']"
                required
                autocomplete="off"
                maxlength="80"
                data-name-part-input
                x-on:input="sanitizeNamePart($event)"
                x-on:blur="$el.value = $el.value.trim()"
                placeholder="e.g. Dela Cruz"
                hint="Letters and spaces only." />

            <x-ui.field
                name="first_name"
                label="First Name"
                :value="$nameComponents['first_name']"
                required
                autocomplete="off"
                maxlength="80"
                data-name-part-input
                x-on:input="sanitizeNamePart($event)"
                x-on:blur="$el.value = $el.value.trim()"
                placeholder="e.g. Juan"
                hint="Letters and spaces only." />

            <x-ui.field
                name="middle_name"
                label="Middle Name"
                :value="$nameComponents['middle_name']"
                autocomplete="off"
                maxlength="80"
                data-name-part-input
                x-on:input="sanitizeNamePart($event)"
                x-on:blur="$el.value = $el.value.trim()"
                placeholder="e.g. Santos"
                hint="Optional. Letters and spaces only." />
        </div>

        <x-ui.field
            name="email"
            label="Email"
            type="email"
            :value="$user?->email"
            required
            autocomplete="off"
            placeholder="e.g. maria.cruz@djnrmhs.gov.ph"
            hint="Used to sign in. Must be unique." />

        <x-ui.field
            name="employee_id"
            label="Employee ID"
            :value="$user?->employee_id ?? 'Generated automatically after creation'"
            disabled
            hint="Assigned automatically by the system and cannot be changed." />

        <x-ui.field
            name="department"
            label="Department"
            type="select"
            :value="$user?->department"
            :options="$departments"
            placeholder="Select a department"
            required />

        <x-ui.field
            name="phone"
            label="Contact Number"
            type="tel"
            :value="$user?->phone"
            required
            inputmode="numeric"
            autocomplete="off"
            minlength="11"
            maxlength="11"
            pattern="09[0-9]{9}"
            title="Enter exactly 11 digits beginning with 09."
            x-on:input="$el.value = $el.value.replace(/[^0-9]/g, '').slice(0, 11)"
            placeholder="09XXXXXXXXX"
            hint="Exactly 11 digits beginning with 09." />

        <div>
            <x-ui.field
                name="role"
                label="Role"
                type="select"
                required
                x-model="role"
                hint="Decides every screen and action this account can reach.">
                @foreach ($roles as $roleOption)
                    <option value="{{ $roleOption->value }}"
                            @selected(old('role', $user?->role?->value) === $roleOption->value)>
                        {{ $roleOption->label() }}
                    </option>
                @endforeach
            </x-ui.field>
            <div class="mt-1.5 flex items-center justify-between" x-show="detail" x-cloak>
                <span class="text-xs text-neutral-500 dark:text-neutral-400 font-mono"
                      x-text="detail ? `${detail.total_count} permissions` : ''"></span>
            </div>
        </div>

        @if ($isEdit)
            @if ($user->isArchived())
                <div>
                    <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-1">Status</label>
                    <div class="flex items-center gap-2 p-2.5 rounded-md bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-xs">
                        <x-ui.badge variant="neutral">Archived</x-ui.badge>
                        <span class="text-neutral-500 dark:text-neutral-400">Archived accounts must be restored through the Archive workspace.</span>
                    </div>
                    <input type="hidden" name="status" value="archived">
                </div>
            @else
                <x-ui.field
                    name="status"
                    label="Status"
                    type="select"
                    required
                    :value="$user->status->value"
                    :options="\App\Enums\UserStatus::options()"
                    hint="Inactive accounts are signed out and cannot sign back in." />
            @endif
        @endif

        <x-ui.field
            name="password"
            label="{{ $isEdit ? 'New Password' : 'Password' }}"
            type="password"
            :required="! $isEdit"
            autocomplete="new-password"
            minlength="8"
            pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,}"
            title="{{ \App\Rules\PasswordStandard::REQUIREMENTS }}"
            x-model="password"
            hint="{{ $isEdit ? 'Leave blank to keep the current password. When changed, all requirements below apply.' : \App\Rules\PasswordStandard::REQUIREMENTS }}" />

        <x-ui.field
            name="password_confirmation"
            label="Confirm Password"
            type="password"
            :required="! $isEdit"
            autocomplete="new-password"
            x-model="passwordConfirmation" />
    </div>

    <div class="mt-4">
        <x-auth.password-requirements />
    </div>

    {{-- Role Permissions Detail Modal --}}
    <x-ui.modal name="form-role-permissions-modal" title="Role permissions" maxWidth="3xl">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400">
                    <x-ui.icon name="shield-check" class="w-5 h-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 id="form-role-permissions-modal-title" class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                            <span x-text="detail?.label"></span> Permissions
                        </h2>
                        <span class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-950/70 dark:text-primary-300 font-mono"
                              x-text="detail ? `${detail.total_count} granted` : ''"></span>
                    </div>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                        Operational abilities and privileges reference
                    </p>
                </div>
            </div>
        </x-slot:header>

        <div class="space-y-4">
            {{-- Only the currently selected role is shown. --}}
            <div class="rounded-lg border border-neutral-200 bg-neutral-50/90 p-3 text-xs text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-300">
                <span class="font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 block mb-0.5">Scope & Responsibilities</span>
                <span x-text="detail?.description"></span>
            </div>

            {{-- Filter Search --}}
            <div class="relative">
                <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                <input
                    type="search"
                    x-model="permissionSearch"
                    placeholder="Search abilities by keyword (e.g. inventory, approval, suppliers)..."
                    class="w-full rounded-md border border-neutral-300 bg-white dark:bg-neutral-800 dark:border-neutral-700 py-1.5 pl-9 pr-3 text-xs text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 focus:outline-none" />
            </div>

            {{-- Categorized permissions list --}}
            <div class="space-y-4 max-h-[48vh] overflow-y-auto pr-1">
                <template x-for="group in visibleGroups()" :key="group.module">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between border-b border-neutral-200 dark:border-neutral-700 pb-1 pt-1">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-200" x-text="group.module"></h4>
                            <span class="rounded-full bg-neutral-100 dark:bg-neutral-800 px-1.5 py-0.5 text-[10px] font-mono text-neutral-500 dark:text-neutral-400"
                                  x-text="`${group.items.length} ${group.items.length === 1 ? 'ability' : 'abilities'}`"></span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <template x-for="item in group.items" :key="item.label">
                                <div class="flex items-start gap-2.5 p-2.5 rounded-lg border border-neutral-200/90 bg-white dark:border-neutral-700/70 dark:bg-neutral-800/50 shadow-2xs">
                                    <x-ui.icon name="check-circle" class="w-4 h-4 shrink-0 text-success-600 dark:text-success-400 mt-0.5" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-medium text-neutral-900 dark:text-neutral-100 leading-snug" x-text="item.label"></p>
                                        <p class="text-[11px] text-neutral-500 dark:text-neutral-400 leading-tight mt-0.5" x-text="item.description"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="py-8 text-center text-xs text-neutral-500 dark:text-neutral-400" x-show="visibleGroups().length === 0">
                    No permissions found matching "<span x-text="permissionSearch" class="font-medium text-neutral-700 dark:text-neutral-300"></span>"
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-between w-full">
                <span class="text-xs text-neutral-500 dark:text-neutral-400 hidden sm:inline">
                    Permissions shown are limited to the selected role.
                </span>
                <x-ui.button type="button" variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'form-role-permissions-modal')">
                    Close
                </x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.modal>
</div>
