@extends('layouts.app')
@push('breadcrumb')<h3 class="content-header-title">{{ $customer->fullname }} — Measurements</h3><p class="mb-0">{{ $customer->phone }} · Enter the sizes taken manually by the tailor.</p>@endpush
@push('breadcrumb-button')<a class="btn btn-primary" href="{{ route('orders.create', ['customer' => $customer->id, 'receipt_order' => request()->input('receipt_order')]) }}">Create order</a>@endpush
@section('content')
<div class="tailor-workflow">

<div class="workflow-steps"><span>1. Customer</span><strong>2. Measurements</strong><span>3. Order & delivery</span></div>
<form class="card" method="post" action="{{ route('measurements.store', $customer) }}">@csrf
@if(request()->boolean('order_flow'))<input type="hidden" name="order_flow" value="1">@endif
@if(request()->filled('receipt_order'))<input type="hidden" name="receipt_order" value="{{ request()->input('receipt_order') }}">@endif
<div class="card-body"><h4>{{ $latest ? 'Update measurements' : 'Enter measurements' }}</h4><p class="text-muted">{{ $latest ? 'The latest sizes are filled in below. Save changes to update the saved measurements. Earlier orders keep their own sizes.' : 'Select a garment below to show its measurement fields, then save the sizes.' }}</p>
@php
    $suggestedGarment = '';
    if ($latest) {
        foreach (config('measurements.required_for_order') as $type => $requiredFields) {
            if (in_array($type, ['kameez', 'shalwar'])) continue;
            if (count(array_intersect($requiredFields, array_keys($latest->values))) === count($requiredFields)) { $suggestedGarment = $type; break; }
        }
    }
    $selectedGarment = old('garment', request()->query('garment', $suggestedGarment));
    if (in_array($selectedGarment, ['kameez', 'shalwar'])) $selectedGarment = 'set';
@endphp
<div class="form-group"><label for="measurement-garment">Measurement type / garment</label><select class="form-control" id="measurement-garment" name="garment" required aria-controls="garment-measurement-fields"><option value="">Choose a garment to enter measurements</option>@foreach(array_diff_key(config('measurements.garments'), array_flip(['kameez', 'shalwar'])) as $value => $label)<option value="{{ $value }}" @if($selectedGarment === $value) selected @endif>{{ config('measurements.garment_symbols.'.$value) ? config('measurements.garment_symbols.'.$value).' ' : '' }}{{ $label }}</option>@endforeach</select><small>Select Suit (Shalwar Kameez) to enter and save kameez and shalwar together. Save the selected garment before moving to another category.</small></div>
<div class="row">
<div class="col-md-3 form-group"><label for="unit">Measurement unit</label><select class="form-control" id="unit" name="unit">@foreach(['inches' => 'Inches', 'cm' => 'Centimetres (cm)'] as $value => $label)<option value="{{ $value }}" @if(old('unit', optional($latest)->unit ?: 'inches') === $value) selected @endif>{{ $label }}</option>@endforeach</select><small>Changing units does not convert entered numbers.</small></div>
<div class="col-md-3 form-group"><label for="convention">Full or half measurement</label><select class="form-control" id="convention" name="convention">@foreach(['full' => 'Full measurement — all the way around', 'half' => 'Half measurement — half of the full size'] as $value => $label)<option value="{{ $value }}" @if(old('convention', optional($latest)->convention ?: 'full') === $value) selected @endif>{{ $label }}</option>@endforeach</select><small>For a 40-inch chest: Full = 40, Half = 20. Changing this option does not convert entered numbers.</small></div>
<div class="col-md-3 form-group"><label for="measured_on">Date measured</label><input class="form-control" type="date" id="measured_on" name="measured_on" value="{{ old('measured_on', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required></div>
<div class="col-md-3 form-group"><label for="taken_by">Tailor's name</label><input class="form-control" id="taken_by" name="taken_by" pattern="{{ \App\Rules\PersonName::HTML_PATTERN }}" title="Enter a name using letters, spaces, apostrophes, hyphens or periods" value="{{ old('taken_by', auth()->user()->name) }}" maxlength="100" required>@error('taken_by')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
</div>
<div class="measurement-help">The Full / Half choice applies to chest, waist, hip / seat, thigh and knee. Shoulder, daman, poncha and pant bottom opening are flat widths; collar / neck, cuff, biceps and armhole are always measured all the way around. Lengths are straight measurements. All values use the selected unit.</div>
<p class="text-muted">Other garment sizes stay saved when the unit and Full / Half setting match. If you change either setting, a separate measurement version is saved; earlier sizes remain in history.</p><p id="measurement-choice-help" class="measurement-help" aria-live="polite">Choose a measurement type above to see its fields.</p>
<div class="row" id="garment-measurement-fields">@foreach(config('measurements.sections') as $section => $fields)
@php
    $entered = 0;
    $sectionHasErrors = false;
    foreach ($fields as $field => $fieldLabel) {
        if (old('values.'.$field, $latest ? ($latest->values[$field] ?? '') : '') !== '' && old('values.'.$field, $latest ? ($latest->values[$field] ?? null) : null) !== null) { $entered++; }
        if ($errors->has('values.'.$field)) { $sectionHasErrors = true; }
    }
@endphp
<details data-section="{{ $section }}" hidden class="col-12 garment-measurement-panel" @if($entered || $sectionHasErrors || (!$latest && $loop->index < 2 && !$errors->any())) open @endif>
<summary>{{ $section }} <span class="garment-field-count">{{ $entered }} sizes entered</span></summary>
<fieldset class="measurement-section"><legend class="sr-only">{{ $section }}</legend>
@foreach(config('measurements.order_sections') as $garmentKey => $garmentSections)
@if($garmentSections === [$section])<p class="text-muted">Required when ordering this garment: {{ implode(', ', array_map(function ($key) use ($fields) { return $fields[$key]; }, config('measurements.required_for_order')[$garmentKey])) }}.</p>@endif
@endforeach
<div class="row">@foreach($fields as $key => $label)
<div class="col-sm-6 col-lg-3 form-group"><label for="{{ $key }}">{{ $label }} <span class="measurement-unit">{{ old('unit', optional($latest)->unit ?: 'inches') }}</span></label><input class="form-control @error('values.'.$key) is-invalid @enderror" id="{{ $key }}" type="number" inputmode="decimal" name="values[{{ $key }}]" min="0.01" max="300" step="0.01" value="{{ old('values.'.$key, $latest ? ($latest->values[$key] ?? '') : '') }}" placeholder="e.g. 38.5"></div>
@endforeach</div></fieldset></details>@endforeach</div>
<div class="row"><div class="col-md-3 form-group"><label for="fit">Fit preference</label><select class="form-control" name="fit" id="fit">@foreach(['regular','slim','loose'] as $fit)<option value="{{ $fit }}" @if(old('fit', optional($latest)->fit ?: 'regular') === $fit) selected @endif>{{ ucfirst($fit) }}</option>@endforeach</select></div><div class="col-md-9 form-group"><label for="notes">Stitching instructions / alterations</label><textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000" placeholder="Collar / lapel style, pockets, buttons, lining, vents, cuffs, special fit instructions…">{{ old('notes', optional($latest)->notes) }}</textarea></div></div>
<div class="workflow-actions"><button class="btn btn-primary" id="save-measurements">Save measurements</button><a class="btn btn-outline-secondary" href="{{ request()->boolean('order_flow') ? route('orders.create', ['customer' => $customer, 'receipt_order' => request()->input('receipt_order')]) : route('customers') }}">{{ request()->boolean('order_flow') ? 'Back to order' : 'Back to customers' }}</a></div>
</div></form>
@if($latest)
<section class="card" id="saved-measurements"><div class="card-body"><h4>Saved measurements</h4><p class="text-muted">Current saved sizes. Each garment appears once.</p>
@include('partials.measurement-summary', ['snapshot' => $latest->snapshot()])
@if($measurements->count() > 1)<a class="btn btn-outline-secondary" href="{{ route('measurements.index', ['customer' => $customer->id, 'garment' => $selectedGarment, 'history' => request()->boolean('history') ? null : 1, 'order_flow' => request()->boolean('order_flow') ? 1 : null, 'receipt_order' => request()->input('receipt_order')]) }}{{ request()->boolean('history') ? '#saved-measurements' : '#measurement-history' }}">{{ request()->boolean('history') ? 'Hide earlier versions' : 'View earlier measurement versions' }}</a>@endif
</div></section>
@if(request()->boolean('history') && $measurements->count() > 1)
<section class="card" id="measurement-history"><div class="card-body"><h4>Earlier measurement versions</h4><p class="text-muted">Previous records are kept for reference. These are not additional garments in the current saved measurements.</p>
@foreach($measurements->skip(1) as $measurement)<details class="measurement-history"><summary>{{ $measurement->measured_on->format('d M Y') }} · {{ $measurement->unit }} · {{ $measurement->taken_by }}</summary>@include('partials.measurement-summary', ['snapshot' => $measurement->snapshot()])</details>@endforeach
</div></section>
@endif
@endif
</div>
@endsection
@push('page-js')<script>
(function () {
    var picker = document.getElementById('measurement-garment');
    var sections = @json(config('measurements.order_sections'));
    var required = @json(config('measurements.required_for_order'));
    var history = @json($measurements->map(function ($measurement) { return $measurement->snapshot(); })->values());
    var drafts = {}, current = picker.value;
    var metadata = ['unit', 'convention', 'fit', 'notes'];
    function remember() {
        if (!current) return;
        var draft = { values: {} };
        document.querySelectorAll('#garment-measurement-fields input').forEach(function (field) { draft.values[field.id] = field.value; });
        metadata.forEach(function (id) { draft[id] = document.getElementById(id).value; });
        drafts[current] = draft;
    }
    function loadSizes() {
        var snapshot = drafts[picker.value] || history.find(function (item) {
            return (required[picker.value] || []).some(function (key) { return item.values[key] != null; });
        });
        document.querySelectorAll('#garment-measurement-fields input').forEach(function (field) { field.value = snapshot && snapshot.values[field.id] != null ? snapshot.values[field.id] : ''; });
        if (snapshot) metadata.forEach(function (id) { document.getElementById(id).value = snapshot[id] || ''; });
        document.querySelectorAll('.measurement-unit').forEach(function(label) { label.textContent = document.getElementById('unit').value; });
    }
    function showGarment() {
        var visible = sections[picker.value] || [];
        document.querySelectorAll('[data-section]').forEach(function (panel) {
            var shown = visible.indexOf(panel.getAttribute('data-section')) >= 0;
            panel.hidden = !shown; panel.open = shown;
            panel.querySelectorAll('input').forEach(function (field) {
                field.disabled = !shown;
                field.required = shown && (required[picker.value] || []).indexOf(field.id) >= 0;
            });
        });
        document.getElementById('measurement-choice-help').hidden = visible.length > 0;
        var alreadySaved = picker.value && history.some(function (item) { return (required[picker.value] || []).length && required[picker.value].every(function (key) { return item.values[key] != null; }); });
        var label = (alreadySaved ? 'Update ' : 'Save ') + (picker.value ? picker.options[picker.selectedIndex].text + ' measurements' : 'measurements');
        document.getElementById('save-measurements').textContent = label + (@json(request()->boolean('order_flow')) ? ' & continue to order' : '');
    }
    picker.addEventListener('change', function () { remember(); loadSizes(); current = picker.value; showGarment(); });
    if (!@json(session()->hasOldInput())) loadSizes();
    showGarment();
    document.getElementById('unit').addEventListener('change', function () { document.querySelectorAll('.measurement-unit').forEach(function (label) { label.textContent = document.getElementById('unit').value; }); });
})();
</script>@endpush
