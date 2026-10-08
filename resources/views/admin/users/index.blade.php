<x-app-layout full-width>
    @php($openCreateUserModal = request()->routeIs('admin.users.create', 'super-admin.users.create') || (old('form_context') === 'create_user' && $errors->any()))
    @php($openEditUserModal = isset($editUser))

    @if ($openCreateUserModal)
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'create-user-modal'))"></div>
    @endif

    @if ($openEditUserModal)
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'edit-user-modal'))"></div>
    @endif

    <x-ui.page-header
        title="User Management"
        :image="asset('img/hims-user-management-hero.png')"
        image-position="right center"
        image-size="auto 100%"
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'User Management' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route(\App\Support\AuthenticationContext::administrationRoute('permissions'))" icon="shield-check">Access Control</x-ui.button>
            <x-ui.button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-user-modal')">Add User</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any() && ! $errors->cancelActivation->any() && old('form_context') !== 'create_user' && ! $openEditUserModal)
        <x-ui.alert variant="danger" title="That change was not applied">
            <ul class="space-y-0.5 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Total accounts" :value="number_format($counts['total'])" icon="users" tone="primary" />
        <x-ui.stat label="Active" :value="number_format($counts['active'])" icon="check-circle" tone="success"
                   hint="Inactive accounts cannot sign in." />
        <x-ui.stat label="Administrators" :value="number_format($counts['administrators'])" icon="shield-check"
                   tone="warning" hint="The system always keeps at least one." />
    </div>

    <x-ui.card title="Find an Account" subtitle="Search by name, email, account ID or department.">
        <form method="GET" action="{{ route(\App\Support\AuthenticationContext::administrationRoute('users.index')) }}"
              class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <x-ui.field
                name="search"
                label="Search"
                :value="$filters['search'] ?? null"
                placeholder="e.g. Cruz, EMP-014, SUP-021" />

            <x-ui.field
                name="role"
                label="Role"
                type="select"
                :value="$filters['role'] ?? null"
                placeholder="All roles"
                :options="$roles" />

            <x-ui.field
                name="status"
                label="Status"
                type="select"
                :value="$filters['status'] ?? null"
                placeholder="All statuses"
                :options="$statuses" />

            <div class="flex items-center gap-2">
                <x-ui.button type="submit" icon="magnifying-glass" data-loading-text="Loading accounts...">Search</x-ui.button>
                @if (array_filter($filters))
                    <x-ui.button variant="secondary" :href="route(\App\Support\AuthenticationContext::administrationRoute('users.index'))">Clear</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    <x-ui.card
        class="users-accounts-table"
        title="Accounts"
        :subtitle="$users->total().' '.\Illuminate\Support\Str::plural('account', $users->total())"
        :padding="false">
        <x-slot:actions>
            <x-ui.button
                type="button"
                variant="secondary"
                size="sm"
                icon="shield-check"
                x-data
                x-on:click="$dispatch('open-modal', 'role-permissions-modal')">
                View Role Permissions
            </x-ui.button>
        </x-slot:actions>

        {{-- Mobile & Tablet card view (visible on screens below lg / 1024px) --}}
        <div class="lg:hidden divide-y divide-neutral-200">
            @forelse ($users as $account)
                @php($nameComponents = $account->nameComponents())
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <x-ui.avatar :user="$account" size="sm" />
                            <div class="min-w-0">
                                <a href="{{ route(\App\Support\AuthenticationContext::administrationRoute('users.show'), $account) }}"
                                   title="{{ $account->name }}"
                                   class="font-medium text-neutral-900 hover:text-primary-700 hover:underline truncate block">
                                    {{ $account->name }}
                                </a>
                                <span class="block text-xs text-neutral-500 truncate">{{ $account->email }}</span>
                            </div>
                        </div>
                        @if ($account->is(auth()->user()))
                            <span class="text-[11px] font-medium text-neutral-400 shrink-0">(you)</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <x-ui.badge :variant="$account->isAdministrator() ? 'primary' : 'neutral'">
                            {{ $account->role->label() }}
                        </x-ui.badge>
                        <x-ui.badge :status="$account->status->value" dot>{{ $account->status->label() }}</x-ui.badge>
                        @if (! $account->isPendingActivation() && ! $account->isCancelled() && ! $account->hasVerifiedEmail())
                            <x-ui.badge status="pending" dot>Pending Email Verification</x-ui.badge>
                        @endif
                        @if ($account->isTemporarilyLocked())
                            <x-ui.badge variant="warning">Temporarily Locked</x-ui.badge>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs text-neutral-600 pt-1">
                        <div>
                            <span class="text-neutral-400">ID:</span>
                            <span class="font-mono font-medium">{{ $account->employee_id ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400">Dept:</span>
                            <span class="font-medium">{{ $account->department ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400">Phone:</span>
                            <span>{{ $account->phone ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400">Sign-in:</span>
                            <span>{{ $account->last_login_at?->format('M d, Y') ?? 'Never' }}</span>
                        </div>
                    </div>

                    {{-- Actions on mobile/tablet: neatly arranged and fully accessible --}}
                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-neutral-100">
                        @if (in_array($account->getKey(), $manageableAccountIds, true))
                            <x-ui.button variant="secondary" size="sm"
                                         :href="route(\App\Support\AuthenticationContext::administrationRoute('users.edit'), $account)" icon="pencil-square">
                                Edit
                            </x-ui.button>

                            @if (! $account->isCancelled() && ! $account->hasVerifiedEmail())
                                <form method="POST" action="{{ route(\App\Support\AuthenticationContext::administrationRoute('users.verification.send'), $account) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm" data-loading-text="Sending activation email...">
                                        Resend Activation
                                    </x-ui.button>
                                </form>
                            @endif

                            @if ($account->isPendingActivation())
                                <x-ui.button
                                    type="button"
                                    variant="danger"
                                    size="sm"
                                    x-on:click="$dispatch('open-cancel-activation', { actionUrl: {{ Illuminate\Support\Js::from(route(\App\Support\AuthenticationContext::administrationRoute('users.cancel-invitation'), $account)) }}, accountName: {{ Illuminate\Support\Js::from($account->name) }}, userId: {{ Illuminate\Support\Js::from($account->getKey()) }} })">
                                    Cancel Activation
                                </x-ui.button>
                            @endif

                            @if (in_array($account->getKey(), $unlockableAccountIds, true))
                                <form method="POST" action="{{ route(\App\Support\AuthenticationContext::administrationRoute('users.unlock'), $account) }}"
                                      data-confirm-title="Unlock account?"
                                      data-confirm-message="This will allow the user to attempt signing in again."
                                      data-confirm-label="Unlock Account">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button
                                        type="submit"
                                        size="sm"
                                        data-loading-text="Unlocking account...">
                                        Unlock
                                    </x-ui.button>
                                </form>
                            @endif

                            @unless ($account->is(auth()->user()) || $account->isPendingActivation())
                                @php($willReinvite = ! $account->isActive() && $account->requiresActivation())
                                <form method="POST" action="{{ route(\App\Support\AuthenticationContext::administrationRoute('users.toggle-status'), $account) }}"
                                      data-confirm-title="{{ $willReinvite ? 'Re-invite user?' : 'Confirm account status change' }}"
                                      data-confirm-message="{{ $willReinvite ? 'This will return the account to Pending Activation and send a new invitation.' : 'Are you sure you want to '.($account->isActive() ? 'deactivate' : 'reactivate').' this user?' }}"
                                      data-confirm-label="{{ $willReinvite ? 'Re-invite' : ($account->isActive() ? 'Deactivate' : 'Reactivate') }}"
                                      @if (auth()->user()?->isSuperAdministrator() && $account->isActive()) data-super-admin-deactivate="true" @endif>
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button
                                        type="submit"
                                        size="sm"
                                        data-loading-text="{{ $willReinvite ? 'Sending invitation...' : 'Updating account...' }}"
                                        :variant="$account->isActive() ? 'secondary' : 'primary'">
                                        {{ $willReinvite ? 'Re-invite' : ($account->isActive() ? 'Deactivate' : 'Reactivate') }}
                                    </x-ui.button>
                                </form>

                                @can(\App\Enums\Permission::ManageArchive->value)
                                    @unless ($account->isProtected())
                                        <x-ui.button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            class="text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                                            @click="$dispatch('open-archive-modal', {
                                                actionUrl: '{{ route(\App\Support\AuthenticationContext::administrationRoute('users.archive'), $account) }}',
                                                title: '{{ addslashes($account->name) }}',
                                                identifier: '{{ $account->accountIdentifierLabel() }}: {{ addslashes($account->employee_id ?? 'N/A') }} · {{ addslashes($account->email) }}',
                                                context: 'Role: {{ addslashes($account->role?->label() ?? 'Staff') }}',
                                                type: 'User Account',
                                                presets: [
                                                    'Employee resignation / separation from hospital',
                                                    'Contract ended / tenure completed',
                                                    'Department transfer / role access revoked',
                                                    'Duplicate user account profile'
                                                ]
                                            })">
                                            Archive
                                        </x-ui.button>
                                    @endunless
                                @endcan
                            @endunless
                        @elseif ($account->isProtected())
                            <x-ui.badge variant="warning">Protected</x-ui.badge>
                        @else
                            <span class="text-xs text-neutral-400">Restricted</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-neutral-500">
                    <x-ui.empty-artwork category="users" size="sm" />
                    <p class="font-semibold text-neutral-700">No accounts match</p>
                    <p class="text-xs text-neutral-500 mt-1">Adjust the filters, or add the first staff account.</p>
                </div>
            @endforelse
        </div>

        {{-- Desktop table view (visible on screens lg / 1024px and wider) --}}
        <div class="hidden lg:block">
            <x-ui.table :sticky-header="false" aria-label="User accounts">
                <x-ui.table.head>
                    <x-ui.table.th class="px-2.5 py-3 xl:px-3 w-20 xl:w-24">Account ID</x-ui.table.th>
                    <x-ui.table.th class="px-2.5 py-3 xl:px-3">Surname</x-ui.table.th>
                    <x-ui.table.th class="px-2.5 py-3 xl:px-3">First Name</x-ui.table.th>
                    <x-ui.table.th class="px-2.5 py-3 xl:px-3 w-16 xl:w-20">Middle Name</x-ui.table.th>
                    <x-ui.table.th class="px-2.5 py-3 xl:px-3">Department</x-ui.table.th>
                    <x-ui.table.th class="px-2.5 py-3 xl:px-3 whitespace-nowrap">Contact Number</x-ui.table.th>
                    <x-ui.table.th class="px-2.5 py-3 xl:px-3">Role</x-ui.table.th>
                    <x-ui.table.th class="px-2.5 py-3 xl:px-3">Status</x-ui.table.th>
                    <x-ui.table.th class="px-2.5 py-3 xl:px-3 w-24 xl:w-28">Last Sign-in</x-ui.table.th>
                    <x-ui.table.th align="right" class="px-2.5 py-3 xl:px-3 hims-sticky-actions">Actions</x-ui.table.th>
                </x-ui.table.head>
                <tbody>
                    @forelse ($users as $account)
                        @php($nameComponents = $account->nameComponents())
                        <x-ui.table.row>
                            <x-ui.table.td muted class="px-2.5 py-2 xl:px-3 xl:py-2.5 whitespace-nowrap">
                                <span class="font-mono text-xs">{{ $account->employee_id ?? '—' }}</span>
                            </x-ui.table.td>

                            <x-ui.table.td class="px-2.5 py-2 xl:px-3 xl:py-2.5">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-ui.avatar :user="$account" size="xs" />
                                    <div class="min-w-0">
                                        <a href="{{ route(\App\Support\AuthenticationContext::administrationRoute('users.show'), $account) }}"
                                           title="{{ $account->name }}"
                                           class="font-medium text-neutral-900 hover:text-primary-700 hover:underline truncate block">
                                            {{ $nameComponents['surname'] ?? $account->name }}
                                        </a>
                                        @if ($account->is(auth()->user()))
                                            <span class="text-[11px] font-medium text-neutral-400 block -mt-0.5">(you)</span>
                                        @endif
                                    </div>
                                </div>
                            </x-ui.table.td>

                            <x-ui.table.td class="px-2.5 py-2 xl:px-3 xl:py-2.5 min-w-0">
                                <span class="text-neutral-900 block truncate">{{ $nameComponents['first_name'] ?? '—' }}</span>
                                <span class="block text-xs text-neutral-500 truncate max-w-[130px] xl:max-w-[180px]" title="{{ $account->email }}">{{ $account->email }}</span>
                            </x-ui.table.td>

                            <x-ui.table.td muted class="px-2.5 py-2 xl:px-3 xl:py-2.5 truncate max-w-[80px]">{{ $nameComponents['middle_name'] ?? '—' }}</x-ui.table.td>

                            <x-ui.table.td muted class="px-2.5 py-2 xl:px-3 xl:py-2.5 truncate max-w-[120px] xl:max-w-[150px]">{{ $account->department ?? '—' }}</x-ui.table.td>

                            <x-ui.table.td muted class="px-2.5 py-2 xl:px-3 xl:py-2.5 whitespace-nowrap font-mono text-xs">{{ $account->phone ?? '—' }}</x-ui.table.td>

                            <x-ui.table.td class="px-2.5 py-2 xl:px-3 xl:py-2.5 whitespace-nowrap">
                                <x-ui.badge :variant="$account->isAdministrator() ? 'primary' : 'neutral'">
                                    {{ $account->role->label() }}
                                </x-ui.badge>
                            </x-ui.table.td>

                            <x-ui.table.td class="px-2.5 py-2 xl:px-3 xl:py-2.5">
                                <x-ui.badge :status="$account->status->value" dot>{{ $account->status->label() }}</x-ui.badge>
                                @if (! $account->isPendingActivation() && ! $account->isCancelled() && ! $account->hasVerifiedEmail())
                                    <x-ui.badge status="pending" dot>Pending Email Verification</x-ui.badge>
                                @endif
                                @if ($account->isTemporarilyLocked())
                                    <x-ui.badge variant="warning" class="mt-1">Locked</x-ui.badge>
                                    <span class="mt-0.5 block text-[11px] text-neutral-500">
                                        Until {{ $account->loginRestrictionUntil()->timezone(config('app.timezone'))->format('M d, g:i A') }}
                                    </span>
                                @endif
                            </x-ui.table.td>

                            <x-ui.table.td muted class="px-2.5 py-2 xl:px-3 xl:py-2.5 whitespace-nowrap">
                                @if ($account->last_login_at)
                                    <span class="block text-xs text-neutral-800">{{ $account->last_login_at->format('M d, Y') }}</span>
                                    <span class="block text-[11px] text-neutral-400">{{ $account->last_login_at->format('g:i A') }}</span>
                                @else
                                    <span class="text-xs text-neutral-400">Never</span>
                                @endif
                            </x-ui.table.td>

                            <x-ui.table.td align="right" class="px-2.5 py-2 xl:px-3 xl:py-2.5 hims-sticky-actions">
                                <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                                    @if (in_array($account->getKey(), $manageableAccountIds, true))
                                        <x-ui.button variant="ghost" size="sm" class="px-2 py-1"
                                                     :href="route(\App\Support\AuthenticationContext::administrationRoute('users.edit'), $account)">
                                            Edit
                                        </x-ui.button>

                                        @if (! $account->isCancelled() && ! $account->hasVerifiedEmail())
                                            <form method="POST" action="{{ route(\App\Support\AuthenticationContext::administrationRoute('users.verification.send'), $account) }}">
                                                @csrf
                                                <x-ui.button type="submit" variant="ghost" size="sm" class="px-2 py-1" data-loading-text="Sending...">
                                                    Resend
                                                </x-ui.button>
                                            </form>
                                        @endif

                                        @if ($account->isPendingActivation())
                                            <x-ui.button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="px-2 py-1 text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                                                x-on:click="$dispatch('open-cancel-activation', { actionUrl: {{ Illuminate\Support\Js::from(route(\App\Support\AuthenticationContext::administrationRoute('users.cancel-invitation'), $account)) }}, accountName: {{ Illuminate\Support\Js::from($account->name) }}, userId: {{ Illuminate\Support\Js::from($account->getKey()) }} })">
                                                Cancel Activation
                                            </x-ui.button>
                                        @endif

                                        @if (in_array($account->getKey(), $unlockableAccountIds, true))
                                            <form method="POST" action="{{ route(\App\Support\AuthenticationContext::administrationRoute('users.unlock'), $account) }}"
                                                  data-confirm-title="Unlock account?"
                                                  data-confirm-message="This will allow the user to attempt signing in again."
                                                  data-confirm-label="Unlock Account">
                                                @csrf
                                                @method('PATCH')
                                                <x-ui.button
                                                    type="submit"
                                                    size="sm"
                                                    class="px-2 py-1"
                                                    data-loading-text="Unlocking account...">
                                                    Unlock
                                                </x-ui.button>
                                            </form>
                                        @endif

                                        {{-- Deactivating yourself is refused by the service;
                                             hide the impossible action here as well. --}}
                                        @unless ($account->is(auth()->user()) || $account->isPendingActivation())
                                            @php($willReinvite = ! $account->isActive() && $account->requiresActivation())
                                            <form method="POST" action="{{ route(\App\Support\AuthenticationContext::administrationRoute('users.toggle-status'), $account) }}"
                                                  data-confirm-title="{{ $willReinvite ? 'Re-invite user?' : 'Confirm account status change' }}"
                                                  data-confirm-message="{{ $willReinvite ? 'This will return the account to Pending Activation and send a new invitation.' : 'Are you sure you want to '.($account->isActive() ? 'deactivate' : 'reactivate').' this user?' }}"
                                                  data-confirm-label="{{ $willReinvite ? 'Re-invite' : ($account->isActive() ? 'Deactivate' : 'Reactivate') }}"
                                                  @if (auth()->user()?->isSuperAdministrator() && $account->isActive()) data-super-admin-deactivate="true" @endif>
                                                @csrf
                                                @method('PATCH')
                                                <x-ui.button
                                                    type="submit"
                                                    size="sm"
                                                    class="px-2 py-1"
                                                    data-loading-text="{{ $willReinvite ? 'Sending invitation...' : 'Updating account...' }}"
                                                    :variant="$account->isActive() ? 'secondary' : 'primary'">
                                                    {{ $willReinvite ? 'Re-invite' : ($account->isActive() ? 'Deactivate' : 'Reactivate') }}
                                                </x-ui.button>
                                            </form>

                                            @can(\App\Enums\Permission::ManageArchive->value)
                                                @unless ($account->isProtected())
                                                    <x-ui.button
                                                        type="button"
                                                        size="sm"
                                                        class="px-2 py-1 text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                                                        variant="ghost"
                                                        @click="$dispatch('open-archive-modal', {
                                                            actionUrl: '{{ route(\App\Support\AuthenticationContext::administrationRoute('users.archive'), $account) }}',
                                                            title: '{{ addslashes($account->name) }}',
                                                            identifier: '{{ $account->accountIdentifierLabel() }}: {{ addslashes($account->employee_id ?? 'N/A') }} · {{ addslashes($account->email) }}',
                                                            context: 'Role: {{ addslashes($account->role?->label() ?? 'Staff') }}',
                                                            type: 'User Account',
                                                            presets: [
                                                                'Employee resignation / separation from hospital',
                                                                'Contract ended / tenure completed',
                                                                'Department transfer / role access revoked',
                                                                'Duplicate user account profile'
                                                            ]
                                                        })">
                                                        Archive
                                                    </x-ui.button>
                                                @endunless
                                            @endcan
                                        @endunless
                                    @elseif ($account->isProtected())
                                        <x-ui.badge variant="warning">Protected</x-ui.badge>
                                    @else
                                        <span class="text-xs text-neutral-400">Restricted</span>
                                    @endif
                                </div>
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty artwork="users"
                            :colspan="10"
                            icon="users"
                            title="No accounts match"
                            message="Adjust the filters, or add the first staff account." />
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        @if ($users->hasPages())
            <x-slot:footer>
                {{ $users->links() }}
            </x-slot:footer>
        @endif
    </x-ui.card>

    @include('admin.users.partials.cancel-activation-modal')

    @unless ($openEditUserModal)
        <x-ui.modal name="create-user-modal" title="Add User" maxWidth="6xl">
            <x-slot:header>
                <div class="flex min-w-0 flex-1 flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                            <x-ui.icon name="user-circle" class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <h2 id="create-user-modal-title" class="text-lg font-semibold text-neutral-950 dark:text-white">Add User</h2>
                            <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Create a new employee account for hospital operations.</p>
                        </div>
                    </div>
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        icon="shield-check"
                        x-on:click="$dispatch('open-modal', 'form-role-permissions-modal')">
                        View Role Permissions
                    </x-ui.button>
                </div>
            </x-slot:header>

            <form id="create-user-form" method="POST" action="{{ route(\App\Support\AuthenticationContext::administrationRoute('users.store')) }}" class="space-y-4" autocomplete="off"
                  @if (auth()->user()?->isSuperAdministrator())
                      data-super-admin-password="create"
                  @else
                      data-confirm-title="Create staff user"
                      data-confirm-message="Create this account as Pending Activation? The user will verify a code and create their own password."
                      data-confirm-label="Create User"
                  @endif>
                @csrf
                <input type="hidden" name="form_context" value="create_user">

                @include('admin.users.partials.form', ['user' => null, 'roles' => $createRoles])
            </form>

            <x-slot:footer>
                <div class="flex w-full flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-start gap-2 rounded-lg bg-primary-50 px-3 py-2.5 text-xs text-primary-800 dark:bg-primary-950/50 dark:text-primary-200">
                        <x-ui.icon name="information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>Employee accounts are provisioned for hospital operations. Activity is recorded in accordance with the <a href="{{ route('privacy.notice', ['return' => url()->current()]) }}" target="_blank" rel="opener" class="font-medium text-primary-700 underline underline-offset-2 hover:text-primary-800 dark:text-primary-300 dark:hover:text-primary-200">Privacy Notice</a>.</p>
                    </div>
                    <div class="flex shrink-0 flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'create-user-modal')">Cancel</x-ui.button>
                        <x-ui.button type="submit" form="create-user-form" icon="plus" data-loading-text="Creating account...">Create Account</x-ui.button>
                    </div>
                </div>
            </x-slot:footer>
        </x-ui.modal>
    @endunless

    @if ($openEditUserModal)
        @php($editRolePermissionsModalName = 'edit-user-role-permissions-'.$editUser->getKey())
        <x-ui.modal
            name="edit-user-modal"
            :title="$editUser->role?->isSupplier() ? 'Edit Supplier User' : 'Edit User'"
            maxWidth="6xl"
            :close-url="route(\App\Support\AuthenticationContext::administrationRoute('users.index'))">
            <x-slot:header>
                <div class="flex min-w-0 flex-1 flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-ui.avatar :user="$editUser" size="lg" class="!h-12 !w-12" />
                        <div class="min-w-0">
                            <h2 id="edit-user-modal-title" class="text-lg font-semibold text-neutral-950 dark:text-white">{{ $editUser->role?->isSupplier() ? 'Edit Supplier User' : 'Edit User' }}</h2>
                        <p class="mt-0.5 truncate text-sm text-neutral-500 dark:text-neutral-400">
                            {{ $editUser->name }} · {{ $editUser->email }}
                        </p>
                        </div>
                    </div>
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        icon="shield-check"
                        x-on:click="$dispatch('open-modal', '{{ $editRolePermissionsModalName }}')">
                        View Role Permissions
                    </x-ui.button>
                </div>
            </x-slot:header>

            <form id="edit-user-form" method="POST"
                  action="{{ route(\App\Support\AuthenticationContext::administrationRoute('users.update'), $editUser) }}"
                  class="space-y-4"
                  autocomplete="off"
                  @if (auth()->user()?->isSuperAdministrator())
                      data-super-admin-password="edit"
                  @else
                      data-confirm-title="Confirm account changes"
                      data-confirm-message="Are you sure you want to save these account changes?"
                      data-confirm-label="Save Changes"
                  @endif>
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <x-ui.alert variant="danger" title="That change was not applied">
                        <ul class="list-inside list-disc space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif

                @if ($editUser->is(auth()->user()))
                    <x-ui.alert variant="warning" title="This is your own account">
                        You cannot remove your own administrator access or deactivate yourself.
                    </x-ui.alert>
                @endif

                @include('admin.users.partials.form', [
                    'user' => $editUser,
                    'roles' => $editRoles,
                    'departments' => $editDepartments,
                ])

            </form>

            <x-slot:footer>
                <div class="flex w-full flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-start gap-2 rounded-lg bg-primary-50 px-3 py-2.5 text-xs text-primary-800 dark:bg-primary-950/50 dark:text-primary-200">
                        <x-ui.icon name="information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>Changes to account identity, access, status, or credentials are recorded in the account audit trail.</p>
                    </div>
                    <div class="flex shrink-0 flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <x-ui.button variant="secondary" :href="route(\App\Support\AuthenticationContext::administrationRoute('users.index'))">Cancel</x-ui.button>
                        <x-ui.button type="submit" form="edit-user-form" data-loading-text="Saving changes...">Save Changes</x-ui.button>
                    </div>
                </div>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Role Permissions Interactive Modal --}}
    <x-ui.modal name="role-permissions-modal" title="Role Permissions Reference" maxWidth="2xl">
        <div x-data="{ activeRole: '{{ \App\Enums\UserRole::SuperAdministrator->value }}' }" class="space-y-4">
            {{-- Role selector tabs --}}
            <div>
                <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider mb-2">Select a Role</p>
                <div class="flex flex-wrap gap-1.5 border-b border-neutral-200 pb-3">
                    @foreach (\App\Enums\UserRole::cases() as $role)
                        <button
                            type="button"
                            x-on:click="activeRole = '{{ $role->value }}'"
                            :class="activeRole === '{{ $role->value }}'
                                ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold shadow-xs'
                                : 'bg-neutral-50 text-neutral-600 border-neutral-200 hover:bg-neutral-100 hover:text-neutral-900'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border text-xs transition-colors">
                            <span>{{ $role->label() }}</span>
                            <span
                                :class="activeRole === '{{ $role->value }}' ? 'bg-primary-200 text-primary-800' : 'bg-neutral-200 text-neutral-600'"
                                class="rounded-full px-1.5 py-0.2 text-[10px] font-mono">
                                {{ count($role->permissions()) }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Role detail panels --}}
            @foreach (\App\Enums\UserRole::cases() as $role)
                <div x-show="activeRole === '{{ $role->value }}'" x-cloak class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-3 bg-neutral-50 rounded-lg border border-neutral-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <x-ui.badge :variant="$role->isAdministrator() ? 'primary' : 'neutral'">
                                    {{ $role->label() }}
                                </x-ui.badge>
                                <span class="text-xs text-neutral-500 font-mono">{{ count($role->permissions()) }} granted permissions</span>
                            </div>
                            <p class="mt-1 text-xs text-neutral-600">{{ $role->description() }}</p>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-2">Granted Permissions</h4>
                        <ul class="grid gap-2 sm:grid-cols-2 text-xs text-neutral-700 max-h-[50vh] overflow-y-auto pr-1">
                            @foreach ($role->permissions() as $permission)
                                <li class="flex items-start gap-2 p-2 rounded-md bg-white border border-neutral-100 hover:border-neutral-200 transition-colors">
                                    <x-ui.icon name="check-circle" class="w-4 h-4 mt-0.5 shrink-0 text-success-600" />
                                    <span class="leading-tight">{{ $permission->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>

        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'role-permissions-modal')">
                Close
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</x-app-layout>
