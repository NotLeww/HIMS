<x-layouts.supplier title="Compliance">
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold text-neutral-950 dark:text-white">Compliance</h1>
        <p class="text-sm text-neutral-600 dark:text-neutral-400">Submit current evidence for hospital verification. Previous records remain in the hospital audit history.</p>
    </div>

    @can(\App\Enums\Permission::SupplierManageProfile->value)
        <x-ui.card title="Upload compliance evidence" class="mt-6">
            <form method="POST" action="{{ route('supplier.compliance.store') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                <x-ui.field name="document_type" label="Document type" placeholder="e.g. FDA License to Operate" required />
                <x-ui.field name="document_number" label="Reference number" />
                <x-ui.field name="issuing_authority" label="Issuing authority" />
                <x-ui.field name="issued_at" label="Issue date" type="date" />
                <x-ui.field name="expires_at" label="Expiration date" type="date" />
                <x-ui.field name="file" label="Evidence file" type="file" accept=".pdf,.jpg,.jpeg,.png" required hint="PDF, JPG, or PNG; maximum 10 MB." />
                <div class="md:col-span-2 xl:col-span-3"><x-ui.field name="notes" label="Notes" type="textarea" rows="2" /></div>
                <div class="md:col-span-2 xl:col-span-3"><x-ui.button type="submit">Submit for review</x-ui.button></div>
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
