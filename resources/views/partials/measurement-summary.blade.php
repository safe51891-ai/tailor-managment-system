@if ($snapshot)
<div class="measurement-meta"><strong>{{ $snapshot['unit'] === 'cm' ? 'Centimetres' : 'Inches' }}</strong> · {{ $snapshot['convention'] === 'half' ? 'Half measurements' : 'Full measurements' }} · {{ ucfirst($snapshot['fit']) }} fit<br>Measured {{ substr((string) $snapshot['measured_on'], 0, 10) }} by {{ $snapshot['taken_by'] }}</div>
<div class="row">@foreach (config('measurements.sections') as $section => $fields)
@if ((!isset($garment) || !isset(config('measurements.order_sections')[$garment]) || in_array($section, config('measurements.order_sections')[$garment])) && count(array_intersect_key($snapshot['values'], $fields)))
<div class="col-md-6"><h5 class="mt-2">{{ $section }}</h5><dl class="measurement-summary">@foreach($fields as $key => $label) @if(isset($snapshot['values'][$key]))<div><dt>{{ $label }}</dt><dd>{{ $snapshot['values'][$key] }} {{ $snapshot['unit'] }}</dd></div>@endif @endforeach</dl></div>
@endif
@endforeach</div>
@if(!empty($snapshot['notes']))<p class="measurement-notes"><strong>Stitching instructions:</strong> {{ $snapshot['notes'] }}</p>@endif
@else<p class="text-muted">No measurements recorded for this order.</p>@endif
