<x-layouts.supplier title="Compliance">
    <section class="relative isolate w-full min-w-0 max-w-none overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-emerald-900/30 dark:bg-neutral-950">
        <img
            src="{{ asset('img/hims-supplier-compliance-hero-light.png') }}"
            alt=""
            class="absolute inset-0 h-full w-full object-cover object-center dark:hidden"
            aria-hidden="true"
        />
        <img
            src="{{ asset('img/hims-supplier-compliance-hero.png') }}"
            alt=""
            class="absolute inset-0 hidden h-full w-full object-cover object-center dark:block"
            aria-hidden="true"
        />
        <div class="absolute inset-0 bg-gradient-to-r from-white via-white/90 to-white/10 dark:hidden"></div>
        <div class="absolute inset-0 hidden bg-gradient-to-r from-neutral-950 via-neutral-950/90 to-emerald-950/25 dark:block"></div>
        <div class="relative flex min-h-36 items-center px-5 py-5 sm:h-36 sm:px-6">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-neutral-950 dark:text-white">Compliance</h1>
                <p class="mt-1 max-w-3xl text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">Submit current evidence for hospital verification. Previous records remain in the hospital audit history.</p>
            </div>
        </div>
    </section>

    @can(\App\Enums\Permission::SupplierManageProfile->value)
        <x-ui.card class="mt-6">
            <x-slot:header>
                <div class="flex items-center gap-3">
                    <span class="h-7 w-1 rounded-full bg-emerald-400" aria-hidden="true"></span>
                    <h2 class="text-lg font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">Upload compliance evidence</h2>
                </div>
            </x-slot:header>

            <form method="POST" action="{{ route('supplier.compliance.store') }}" enctype="multipart/form-data" class="grid gap-x-6 gap-y-5 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                <x-ui.field name="document_type" label="Document type" icon="document-text" placeholder="e.g. FDA License to Operate" required />
                <x-ui.field name="document_number" label="Reference number" icon="finger-print" placeholder="Enter reference number" />
                <x-ui.field name="issuing_authority" label="Issuing authority" icon="shield-check" placeholder="Enter issuing authority" />
                <x-ui.field name="issued_at" label="Issue date" type="date" />
                <x-ui.field name="expires_at" label="Expiration date" type="date" />
                <x-ui.field name="file" label="Evidence file" type="file" accept=".pdf,.jpg,.jpeg,.png" required hint="PDF, JPG, or PNG; maximum 10 MB." />
                <div class="md:col-span-2 xl:col-span-3"><x-ui.field name="notes" label="Notes" type="textarea" rows="2" placeholder="Add relevant compliance notes" /></div>
                <div class="md:col-span-2 xl:col-span-3">
                    <x-ui.button type="submit" size="lg" icon="paper-airplane" class="w-full px-6 sm:w-auto" data-loading-text="Submitting...">Submit for review</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endcan

    <x-ui.card title="Current documents" class="mt-6">
        <x-ui.table>
            <x-slot:head>
                <x-ui.table.th>Document</x-ui.table.th>
                <x-ui.table.th>Validity</x-ui.table.th>
                <x-ui.table.th>Status</x-ui.table.th>
            </x-slot:head>
            @forelse($documents as $document)
                <x-ui.table.row>
                    <x-ui.table.td>
                        <a href="{{ route('supplier.compliance.download', $document) }}" class="font-medium text-primary-700 hover:underline dark:text-primary-300">{{ $document->document_type }}</a>
                        <span class="block text-xs text-neutral-500">{{ $document->document_number ?: $document->original_name }}</span>
                    </x-ui.table.td>
                    <x-ui.table.td>{{ $document->expires_at?->format('M j, Y') ?? 'No expiration recorded' }}</x-ui.table.td>
                    <x-ui.table.td><x-ui.badge :status="$document->isExpired() ? 'expired' : $document->verification_status->value" /></x-ui.table.td>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="3">No current compliance documents.</x-ui.table.empty>
            @endforelse
        </x-ui.table>
        <div class="mt-4">{{ $documents->links() }}</div>
    </x-ui.card>
</x-layouts.supplier>
