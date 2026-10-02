@if ($errors->any())
    <x-ui.alert variant="danger" title="Please review the information below." {{ $attributes }}>
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
