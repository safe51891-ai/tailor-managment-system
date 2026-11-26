@include('partials.print-shop-heading', ['documentHeading' => 'Karigar measurement sheet · Cutting & stitching'])
<section class="tracking-block" aria-label="Order tracking code"><span>ORDER / BUNDLE CODE</span><strong class="tracking-code">{{ $order->tracking_code }}</strong></section>
<dl class="document-details work-order-details">
    <div><dt>Customer</dt><dd>{{ optional($order->customer)->fullname ?: 'Customer unavailable' }}</dd></div>
    <div><dt>Assigned karigar</dt><dd>{{ optional($order->karigar)->fullname ?: 'Unassigned — assign a karigar before handing over' }}</dd></div>
    <div><dt>Garment</dt><dd>{{ config('measurements.garments')[$order->garment] ?? 'Garment not specified' }}</dd></div>
    <div><dt>Quantity</dt><dd>{{ $order->quantity }} {{ $order->garment === 'set' ? 'suit(s)' : 'piece(s)' }}</dd></div>
    <div><dt>Date received</dt><dd>{{ \Carbon\Carbon::parse($order->recieved_on)->format('d M Y') }}</dd></div>
    <div><dt>Delivery / collection date</dt><dd><strong>{{ \Carbon\Carbon::parse($order->collecting_on)->format('d M Y') }}</strong></dd></div>
    @if($order->priority === 'emergency')<div class="urgent"><dt>Priority</dt><dd><strong>EMERGENCY</strong></dd></div>@endif
</dl>
<section class="work-measurements"><h2>Measurements for this order</h2>
@include('partials.measurement-summary', ['snapshot' => $order->measurement_snapshot, 'garment' => $order->garment])
@if($order->measurement_snapshot)<p class="cutting-note">Full / Half applies to chest, waist, hip, thigh and knee. Shoulder, daman and bottom openings are flat widths. Neck, cuff, biceps and armhole are full-round measurements.</p>@endif
</section>
@if($order->description || !empty($order->stitching_styles))
<section class="work-instructions"><h2>Cutting &amp; stitching instructions</h2>
@if($order->description)<p><strong>Order notes:</strong> {{ $order->description }}</p>@endif
<dl class="document-details">@foreach(config('tailoring.styles') as $key => $style)@if(!empty($order->stitching_styles[$key]))<div><dt>{{ $style['label'] }}</dt><dd>{{ $style['options'][$order->stitching_styles[$key]] ?? $order->stitching_styles[$key] }}</dd></div>@endif @endforeach</dl>
@if(!empty($order->stitching_styles['instructions']))<p><strong>Stitching notes:</strong> {{ $order->stitching_styles['instructions'] }}</p>@endif
</section>
@endif
<footer class="document-footer"><p>Keep this sheet with bundle {{ $order->tracking_code }}. Sizes are the saved measurement copy for this order.</p></footer>
