@extends('layouts.app')
@push('breadcrumb')<h3 class="content-header-title">{{ $title }}</h3><p class="mb-0">Choose a customer, enter garments and measurements, then save the complete order.</p>@endpush
@section('content')
<div class="tailor-workflow">

<div class="workflow-steps"><span>1. Customer</span><strong>2. Garments, measurements & order</strong></div>
@if($receiptOrder ?? null)<div class="measurement-help">Adding another garment to customer receipt <strong>{{ \App\Models\Orders::trackingCodeFor($receiptOrder->receiptRootId()) }}</strong>. Choose this garment's quantity, dates, price and karigar below.</div>@endif
@include('partials.order-customer-selection')
@if($customer)
<form class="card" method="post" action="{{ $order->exists ? route('orders.update', $order) : route('orders') }}">@csrf @if($order->exists) @method('PUT') @endif
@if($receiptOrder ?? null)<input type="hidden" name="receipt_order" value="{{ $receiptOrder->id }}">@endif
<input type="hidden" name="customer" value="{{ $customer->id }}"><div class="card-body">
@if(!$order->exists)<p class="measurement-help">Enter the first garment below. Use <strong>Add garment to this order</strong> at the bottom for a waskat, shirt, pant or another suit. The customer is entered once.</p>@endif
<div class="row"><div class="col-md-6 form-group"><label for="garment">Garment</label><select class="form-control" id="garment" name="garment">@if($order->exists && in_array($order->garment, ['kameez', 'shalwar']))<option value="{{ $order->garment }}" selected>{{ config('measurements.garments')[$order->garment] }} (existing order)</option>@endif
@foreach(array_diff_key(config('measurements.garments'), array_flip(['kameez', 'shalwar'])) as $value => $label)<option value="{{ $value }}" @if(old('garment', request()->query('garment', $order->garment ?: 'set')) === $value) selected @endif>{{ config('measurements.garment_symbols.'.$value) ? config('measurements.garment_symbols.'.$value).' ' : '' }}{{ $label }}</option>@endforeach</select></div>
@if($order->exists)
<div class="col-md-6 form-group"><label for="measurement_id">Measurements to use</label><select class="form-control" name="measurement_id" id="measurement_id" @if(!$order->measurement_snapshot || (int)$order->customer_id !== (int)$customer->id) required @endif>
@if($order->measurement_snapshot && (int)$order->customer_id === (int)$customer->id)<option value="">Keep the measurements saved with this order</option>@else<option value="">Choose saved measurements</option>@endif
@foreach($measurements as $measurement)<option value="{{ $measurement->id }}" @if((string) old('measurement_id', $order->exists ? '' : optional($measurements->first())->id) === (string) $measurement->id) selected @endif>{{ $measurement->measured_on->format('d M Y') }} · {{ $measurement->unit }} · {{ $measurement->taken_by }} @if($loop->first) (latest) @endif</option>@endforeach</select><a class="d-inline-block mt-1" href="{{ route('measurements.index', ['customer' => $customer, 'order_flow' => $order->exists ? null : 1, 'receipt_order' => optional($receiptOrder ?? null)->id]) }}">Enter / update customer measurements</a></div>

@endif
</div>
@if(!$order->exists)
<div class="row measurement-help"><div class="col-md-6 form-group"><label for="measurement-unit">Measurement unit</label><select class="form-control" id="measurement-unit" name="measurement_unit"><option value="inches" @if(old('measurement_unit', optional($measurements->first())->unit ?: 'inches') === 'inches') selected @endif>Inches</option><option value="cm" @if(old('measurement_unit', optional($measurements->first())->unit) === 'cm') selected @endif>Centimetres</option></select></div><div class="col-md-6 form-group"><label for="measurement-convention">Full or half measurements</label><select class="form-control" id="measurement-convention" name="measurement_convention"><option value="full" @if(old('measurement_convention', optional($measurements->first())->convention ?: 'full') === 'full') selected @endif>Full — around the body</option><option value="half" @if(old('measurement_convention', optional($measurements->first())->convention) === 'half') selected @endif>Half — half of the round measurement</option></select></div><p class="col-12 mb-0">Applies to chest, waist, hip, thigh and knee. Shoulder, daman and bottom opening are flat widths. Changing this setting clears entered sizes; it does not convert them.</p></div>
@include('partials.order-measurement-inputs', ['measurementPrefix' => 'measurement_values', 'measurementIdPrefix' => 'order-size-', 'measurementDraft' => old('measurement_values', optional($measurements->first())->values ?: [])])
@endif
<section class="stitching-options"><h4>Stitching preferences / سلائی کی تفصیل</h4><p class="text-muted">Record the customer's choice for this order. Leave an option blank when it does not apply.</p><div class="row">
@foreach(config('tailoring.styles') as $key => $style)<div class="col-md-6 form-group" data-style="{{ $key }}"><label for="style-{{ $key }}">{{ $style['label'] }}</label><select class="form-control" id="style-{{ $key }}" name="styles[{{ $key }}]"><option value="">Not specified</option>@foreach(array_diff_key($style['options'], ['custom' => true]) as $value => $label)<option value="{{ $value }}" @if(old('styles.'.$key, $order->stitching_styles[$key] ?? '') === $value) selected @endif>{{ $label }}</option>@endforeach</select></div>@endforeach
</div></section>
@if($order->exists)<div id="measurement-preview" class="measurement-help" aria-live="polite"></div>@endif
<div class="row">
<div class="col-md-6 form-group"><label for="karigar_id">Assign karigar / کاریگر</label><select class="form-control" id="karigar_id" name="karigar_id"><option value="">Unassigned — assign later</option>@foreach($karigars as $karigar)<option value="{{ $karigar->id }}" @if((string)old('karigar_id', $order->karigar_id) === (string)$karigar->id) selected @endif>{{ $karigar->fullname }} — {{ config('tailoring.pay_methods')[$karigar->pay_method] }}</option>@endforeach
@if($order->karigar && !$karigars->contains('id', $order->karigar_id))<option value="{{ $order->karigar_id }}" selected>{{ $order->karigar->fullname }} (archived / no longer assignable)</option>@endif</select><small>Choose a karigar before marking work Ready / Delivered. <a href="{{ route('staff') }}">Manage karigars & rates</a></small></div>
<div class="col-md-6 form-group"><label for="quantity">Quantity — suits / garment pieces</label><input class="form-control" id="quantity" name="quantity" type="number" min="1" max="999" step="1" value="{{ old('quantity', $order->quantity ?: 1) }}" required><small>Suit (Shalwar Kameez) includes one kameez and one shalwar together. Quantity 1 means one complete suit. Total price below covers the entire quantity.</small></div>
@if($order->exists && !$order->completed_at && in_array($order->status, ['ready','delivered']))<div class="col-12 measurement-help">This older completed order has no recorded completion date. It is excluded from work-pay totals.</div>@endif
@if($order->completed_at)<div class="col-12 measurement-help">Completed on {{ $order->completed_at->format('d M Y') }}. Karigar, garment, quantity, customer and price are kept unchanged to protect saved earnings.</div>@endif
<div class="col-md-6 form-group"><label for="order-priority">Order priority</label><select class="form-control" id="order-priority" name="priority">@foreach(config('tailoring.priorities') as $value => $label)<option value="{{ $value }}" @if(old('priority', $order->priority ?: 'normal') === $value) selected @endif>{{ $label }}</option>@endforeach</select><small id="priority-help" aria-live="polite">Normal stitching schedule.</small></div>
@foreach(['recieved_on' => 'Date received', 'collecting_on' => 'Completion / delivery date'] as $field => $label)<div class="col-md-6 form-group"><label for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="date" value="{{ old($field, $order->$field ?: date('Y-m-d')) }}" required></div>@endforeach
<div class="col-md-6 form-group"><label for="receiver">Received by</label><input class="form-control" id="receiver" name="receiver" pattern="{{ \App\Rules\PersonName::HTML_PATTERN }}" title="Enter a name using letters, spaces, apostrophes, hyphens or periods" value="{{ old('receiver', $order->recieved_by ?: auth()->user()->name) }}" maxlength="100" required>@error('receiver')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div class="col-md-6 form-group"><label for="order-status">Stitching status</label><select class="form-control" name="status" id="order-status">@foreach(config('measurements.statuses') as $value => $label)<option value="{{ $value }}" @if(old('status', $order->status ?: 'received') === $value) selected @endif>{{ $label }}</option>@endforeach</select></div>
@foreach(['amount_charged' => 'Total price', 'amount_paid' => 'Amount paid'] as $field => $label)<div class="col-md-6 form-group"><label for="{{ $field }}">{{ $label }}</label><input class="form-control" type="number" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $order->$field ?: 0) }}" min="0" max="999999999.99" step="0.01" required></div>@endforeach
@if(!$order->exists)<div class="col-12"><p class="text-muted">Price and paid amount above are for the first garment's quantity. Enter each additional garment's price and payment below; the receipt combines them.</p></div>@endif
<div class="col-12"><p id="karigar-pay-preview" class="measurement-help" aria-live="polite"></p></div>
<div class="col-12"><p class="balance-preview">Remaining balance: <strong id="balance-value">0.00</strong></p></div>
</div>
@if(!$order->exists)
<section class="additional-garments"><h4>More garments for this customer</h4><p class="text-muted">Customer details, received date, delivery date and initial status apply to all garments. Each garment has its own quantity, payment and karigar.</p>
<div id="extra-garments">@foreach(is_array(old('items')) ? old('items') : [] as $itemIndex => $item)@include('partials.order-extra-garment')@endforeach</div>
<template id="extra-garment-template">@include('partials.order-extra-garment', ['itemIndex' => '__INDEX__', 'item' => []])</template>
<button class="btn btn-outline-primary mb-2" id="add-order-garment" type="button">+ Add garment to this order</button>
<p class="measurement-help" id="booking-totals" aria-live="polite"></p></section>
@endif
<div class="workflow-actions"><button class="btn btn-primary">{{ $order->exists ? 'Save order' : 'Save complete customer order' }}</button><a class="btn btn-outline-secondary" href="{{ route('orders') }}">Cancel</a></div>
</div></form>
@endif</div>
@endsection
@push('page-js')
@if($customer)<script>
(function () {
    var snapshots = @json($measurements->mapWithKeys(function ($measurement) { return [$measurement->id => $measurement->snapshot()]; }));
    var existing = @json((int)$order->customer_id === (int)$customer->id ? $order->measurement_snapshot : null);
    var sections = @json(config('measurements.sections'));
    var garmentSections = @json(config('measurements.order_sections'));
    var requiredSizes = @json(config('measurements.required_for_order'));
    var editors = [];
    function setupSizes(editor, garment) {
        editor.querySelectorAll('[data-size-unit]').forEach(function (label) { label.textContent = document.getElementById('measurement-unit').value; });
        function update() {
            editor.querySelectorAll('[data-size-section]').forEach(function (section) {
                var active = (garmentSections[garment.value] || []).indexOf(section.getAttribute('data-size-section')) >= 0;
                section.hidden = !active;
                section.querySelectorAll('input').forEach(function (input) {
                    input.disabled = !active;
                    input.required = active && (requiredSizes[garment.value] || []).indexOf(input.getAttribute('data-size-key')) >= 0;
                    input.closest('.form-group').querySelector('[data-size-required]').textContent = input.required ? ' *' : '';
                });
            });
        }
        garment.addEventListener('change', update); update(); editors.push(editor);
    }
    var mainEditor = document.getElementById('order-size-editor');
    if (mainEditor) setupSizes(mainEditor, document.getElementById('garment'));
    ['measurement-unit', 'measurement-convention'].forEach(function (id) {
        var control = document.getElementById(id);
        if (control) control.addEventListener('change', function () {
            editors.forEach(function (editor) { editor.querySelectorAll('input').forEach(function (input) { input.value = ''; }); });
            document.querySelectorAll('[data-size-unit]').forEach(function (label) { label.textContent = document.getElementById('measurement-unit').value; });
        });
    });
    var extraContainer = document.getElementById('extra-garments');
    if (extraContainer) {
        var extraTemplate = document.getElementById('extra-garment-template');
        var addGarment = document.getElementById('add-order-garment');
        var nextItem = @json(is_array(old('items')) && count(old('items')) ? max(array_map('intval', array_keys(old('items')))) + 1 : 0);
        function cents(input) { var amount = Number(input.value); return Number.isFinite(amount) ? Math.round(amount * 100) : 0; }
        function bookingTotals() {
            var charged = cents(document.getElementById('amount_charged')), paid = cents(document.getElementById('amount_paid'));
            extraContainer.querySelectorAll('[data-item-money]').forEach(function (input) {
                if (input.getAttribute('data-item-money') === 'amount_charged') charged += cents(input); else paid += cents(input);
            });
            var count = extraContainer.querySelectorAll('[data-extra-garment]').length;
            document.getElementById('booking-totals').textContent = 'Complete order: ' + (count + 1) + ' garment entries · Total PKR ' + (charged / 100).toFixed(2) + ' · Paid PKR ' + (paid / 100).toFixed(2) + ' · Balance PKR ' + ((charged - paid) / 100).toFixed(2);
            addGarment.disabled = count >= 19;
        }
        function setupExtra(row) {
            var garment = row.querySelector('[data-item-garment]');
            var editor = row.querySelector('[data-size-editor]');
            // A new row uses saved sizes only when their units still match this order.
            if (row.getAttribute('data-new-row') === 'true') {
                var latest = @json(optional($measurements->first())->snapshot());
                var compatible = latest && latest.unit === document.getElementById('measurement-unit').value && latest.convention === document.getElementById('measurement-convention').value;
                editor.querySelectorAll('input').forEach(function (input) { input.value = compatible ? (latest.values[input.getAttribute('data-size-key')] || '') : ''; });
            }
            setupSizes(editor, garment);
            function showExtraStyles() {
                row.querySelectorAll('[data-item-style]').forEach(function (group) {
                    var key = group.getAttribute('data-item-style'), type = garment.value;
                    var applicable = key === 'daman' ? ['set','kameez'].indexOf(type) >= 0 : key === 'shalwar' ? ['set','shalwar'].indexOf(type) >= 0 : key === 'cuff' ? ['set','kameez','shirt','sleeved_waistcoat'].indexOf(type) >= 0 : ['shalwar','pant'].indexOf(type) < 0;
                    group.hidden = !applicable; group.querySelector('select').disabled = !applicable;
                });
            }
            garment.addEventListener('change', showExtraStyles); showExtraStyles();
            row.querySelector('[data-remove-garment]').addEventListener('click', function () { row.remove(); bookingTotals(); addGarment.focus(); });
            row.querySelectorAll('[data-item-money]').forEach(function (input) { input.addEventListener('input', bookingTotals); });
        }
        extraContainer.querySelectorAll('[data-extra-garment]').forEach(setupExtra);
        addGarment.addEventListener('click', function () {
            if (extraContainer.querySelectorAll('[data-extra-garment]').length >= 19) return;
            var fragment = extraTemplate.content.cloneNode(true);
            fragment.querySelectorAll('[id], [name], label[for]').forEach(function (element) {
                ['id','name','for'].forEach(function (attribute) {
                    if (element.hasAttribute(attribute)) element.setAttribute(attribute, element.getAttribute(attribute).replace(/__INDEX__/g, String(nextItem)));
                });
            });
            nextItem++;
            var row = fragment.querySelector('[data-extra-garment]'); row.setAttribute('data-new-row', 'true'); extraContainer.appendChild(fragment); setupExtra(row); bookingTotals(); row.querySelector('[data-item-garment]').focus();
        });
        ['amount_charged','amount_paid'].forEach(function (id) { document.getElementById(id).addEventListener('input', bookingTotals); });
        bookingTotals();
    }
    var garmentPicker = document.getElementById('garment');
    var picker = document.getElementById('measurement_id');
    function preview() {
        var garment = garmentPicker.value;
        document.querySelectorAll('[data-style]').forEach(function (group) {
            var key = group.getAttribute('data-style');
            var applicable = key === 'daman' ? ['set','kameez'].indexOf(garment) >= 0 : key === 'shalwar' ? ['set','shalwar'].indexOf(garment) >= 0 : key === 'cuff' ? ['set','kameez','shirt','sleeved_waistcoat'].indexOf(garment) >= 0 : ['shalwar','pant'].indexOf(garment) < 0;
            group.hidden = !applicable; group.querySelector('select').disabled = !applicable;
        });
        if (!picker) return;
        var snapshot = picker.value ? snapshots[picker.value] : existing;
        var box = document.getElementById('measurement-preview');
        box.textContent = '';
        if (!snapshot) { box.textContent = 'Select measurements above to check the sizes.'; return; }
        var heading = document.createElement('strong');
        heading.textContent = snapshot.unit + ' · ' + (snapshot.convention === 'half' ? 'Half measurements' : 'Full measurements') + ' · ' + snapshot.fit + ' fit';
        box.appendChild(heading);
        (garmentSections[garmentPicker.value] || Object.keys(sections)).forEach(function (section) {
            var populated = Object.keys(sections[section]).filter(function (key) { return snapshot.values[key] != null; });
            var line = document.createElement('p');
            line.className = 'mb-0 mt-1';
            line.textContent = section + ': ' + (populated.length ? populated.map(function (key) { return sections[section][key] + ' ' + snapshot.values[key]; }).join(' · ') : 'No sizes recorded — update customer measurements.');
            box.appendChild(line);
        });
        if (snapshot.notes) { var notes = document.createElement('p'); notes.textContent = 'Instructions: ' + snapshot.notes; box.appendChild(notes); }
    }
    if (picker) picker.addEventListener('change', preview); garmentPicker.addEventListener('change', preview); preview();
    @if($order->completed_at)
    ['garment', 'karigar_id'].forEach(function (id) {
        var field = document.getElementById(id), hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = field.name; hidden.value = field.value;
        field.form.appendChild(hidden); field.disabled = true;
    });
    ['quantity', 'amount_charged'].forEach(function (id) { document.getElementById(id).readOnly = true; });
    document.getElementById('order-status').querySelectorAll('option').forEach(function(option){ if(['ready','delivered'].indexOf(option.value)<0) option.disabled=true; });
    @endif
    var priorityPicker = document.getElementById('order-priority');
    function priorityLabel() { var urgent = priorityPicker.value === 'emergency'; priorityPicker.classList.toggle('emergency-control', urgent); document.getElementById('priority-help').textContent = urgent ? 'Emergency — urgent stitching requested.' : 'Normal stitching schedule.'; }
    priorityPicker.addEventListener('change', priorityLabel); priorityLabel();
    function dateLimits() { document.getElementById('collecting_on').min = document.getElementById('recieved_on').value; }
    document.getElementById('recieved_on').addEventListener('change', dateLimits); dateLimits();
    var payRates = @json($karigars->mapWithKeys(function ($karigar) { return [$karigar->id => ['method' => $karigar->pay_method, 'rate' => (float)$karigar->pay_rate]]; }));
    var savedPay = @json($order->completed_at ? ['amount' => $order->employee_earning, 'completed' => true] : null);
    function payPreview() {
        var box = document.getElementById('karigar-pay-preview');
        if (savedPay) { box.textContent = 'Saved work earnings: PKR ' + Number(savedPay.amount).toFixed(2) + '. Salary accrual is shown separately on the dashboard.'; return; }
        var pay = payRates[document.getElementById('karigar_id').value];
        if (!pay) { box.textContent = 'Assign a karigar to calculate employee work pay.'; return; }
        if (pay.method === 'salary') { box.textContent = 'Monthly salary: PKR ' + pay.rate.toFixed(2) + '. Accrued salary appears on the dashboard; no extra per-order pay.'; return; }
        if (pay.method === 'per_suit' && garmentPicker.value !== 'set') { box.textContent = 'This karigar is paid per complete shalwar kameez suit. This garment earns PKR 0.00 under that method.'; return; }
        var amount = pay.method === 'percentage' ? Number(document.getElementById('amount_charged').value) * pay.rate / 100 : pay.method === 'per_suit' ? Number(document.getElementById('quantity').value) * pay.rate : pay.rate;
        box.textContent = 'Estimated work pay at the current rate: PKR ' + amount.toFixed(2) + '. Earned once when marked Ready / Delivered.';
    }
    ['karigar_id','quantity','garment','amount_charged'].forEach(function(id){ document.getElementById(id).addEventListener('input', payPreview); document.getElementById(id).addEventListener('change', payPreview); }); payPreview();
    function balance() { var charged = Number(document.getElementById('amount_charged').value), paid = Number(document.getElementById('amount_paid').value); document.getElementById('balance-value').textContent = (charged - paid).toFixed(2); }
    ['amount_charged','amount_paid'].forEach(function (id) { document.getElementById(id).addEventListener('input', balance); }); balance();
})();
</script>@endif
@endpush
