<section data-size-editor @if($measurementIdPrefix === 'order-size-') id="order-size-editor" @endif>
<h4>Garment measurements</h4><p class="text-muted">Saved sizes are filled in when available. Enter or change sizes here. Fields marked * are required. Saving the order also saves these customer measurements.</p>
@foreach(config('measurements.sections') as $section => $fields)
<fieldset class="mb-2" data-size-section="{{ $section }}" hidden><legend class="h5">{{ $section }}</legend><div class="row">
@foreach($fields as $key => $label)
<div class="col-md-4 form-group"><label for="{{ $measurementIdPrefix.$key }}">{{ $label }}<span data-size-required></span> <small data-size-unit>{{ old('measurement_unit', optional($measurements->first())->unit ?: 'inches') }}</small></label><input class="form-control" id="{{ $measurementIdPrefix.$key }}" name="{{ $measurementPrefix }}[{{ $key }}]" data-size-key="{{ $key }}" type="number" min="0.01" max="300" step="0.01" placeholder="e.g. 38.5" value="{{ $measurementDraft[$key] ?? '' }}" disabled></div>
@endforeach
</div></fieldset>
@endforeach
</section>
