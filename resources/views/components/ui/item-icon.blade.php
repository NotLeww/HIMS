@props(['item'])

@php
    $searchable = str(collect([
        $item->name,
        $item->sku,
        $item->generic_name,
        $item->brand_name,
        $item->dosage_form_strength,
        $item->regulatory_category,
        $item->relationLoaded('category') ? $item->category?->name : null,
        $item->relationLoaded('category') ? $item->category?->code : null,
    ])->filter()->implode(' '))->lower()->toString();

    $icon = match (true) {
        str_contains($searchable, 'glove'), str_contains($searchable, 'hand') => 'hand-raised',
        str_contains($searchable, 'mask'), str_contains($searchable, 'respirator') => 'medical-face-mask',
        str_contains($searchable, 'syringe'), str_contains($searchable, 'needle') => 'medical-syringe',
        str_contains($searchable, 'cannula'), str_contains($searchable, 'infusion'), str_contains($searchable, 'saline'), str_contains($searchable, 'iv fluid') => 'medical-iv-bag',
        str_contains($searchable, 'ampoule'), str_contains($searchable, 'ampule'), str_contains($searchable, 'vial'), str_contains($searchable, 'injectable') => 'medical-vial',
        str_contains($searchable, 'alcohol'), str_contains($searchable, 'antiseptic'), str_contains($searchable, 'disinfectant'), str_contains($searchable, 'solution'), str_contains($searchable, 'syrup'), str_contains($searchable, 'drops'), str_contains($searchable, 'ointment'), str_contains($searchable, 'cream') => 'medical-bottle',
        str_contains($searchable, 'gauze'), str_contains($searchable, 'dressing'), str_contains($searchable, 'bandage'), str_contains($searchable, 'wound pad') => 'medical-bandage',
        str_contains($searchable, 'suture'), str_contains($searchable, 'stitch') => 'medical-suture',
        str_contains($searchable, 'catheter'), str_contains($searchable, 'tube') => 'medical-catheter',
        str_contains($searchable, 'electrode'), str_contains($searchable, 'ecg'), str_contains($searchable, 'monitor') => 'medical-monitor',
        str_contains($searchable, 'test strip'), str_contains($searchable, 'reagent'), str_contains($searchable, 'diagnostic'), str_contains($searchable, 'laboratory') => 'beaker',
        str_contains($searchable, 'tablet'), str_contains($searchable, 'capsule'), str_contains($searchable, 'caplet'), str_contains($searchable, 'pill'), str_contains($searchable, 'medicine'), str_contains($searchable, 'medication'), str_contains($searchable, 'drug'), str_contains($searchable, 'pharma') => 'capsule',
        str_contains($searchable, 'gown'), str_contains($searchable, 'ppe'), str_contains($searchable, 'protective') => 'shield-check',
        str_contains($searchable, 'device'), str_contains($searchable, 'equipment'), str_contains($searchable, 'machine'), str_contains($searchable, 'pump') => 'cpu-chip',
        str_contains($searchable, 'surgical'), str_contains($searchable, 'instrument'), str_contains($searchable, 'scalpel'), str_contains($searchable, 'forceps'), str_contains($searchable, 'scissor') => 'adjustments-horizontal',
        default => 'tag',
    };
@endphp

<span {{ $attributes->merge([
    'class' => 'hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-600 sm:flex dark:bg-neutral-800 dark:text-neutral-300',
    'data-item-icon' => $icon,
]) }}>
    <x-ui.icon :name="$icon" class="h-5 w-5" />
</span>
