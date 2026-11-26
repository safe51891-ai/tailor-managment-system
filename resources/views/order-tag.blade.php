@extends('layouts.order-print', ['documentType' => 'clothes-tag', 'printLabel' => 'Print clothes tag', 'printHelp' => 'Attach this tag to the clothes bundle. Print extra copies if the bundle is split.'])
@section('document')
<header class="document-heading"><div class="shop-name">{{ config('app.name') }}</div><h1>Clothes bundle tag</h1></header>
<section class="tracking-block" aria-label="Order tracking code"><span>TRACKING CODE</span><strong class="tracking-code">{{ $order->tracking_code }}</strong></section>
<dl class="document-details">
    <div><dt>Customer</dt><dd>{{ optional($order->customer)->fullname ?: 'Customer unavailable' }}</dd></div>
    <div><dt>Garment</dt><dd>{{ config('measurements.garments')[$order->garment] ?? 'Garment not specified' }}</dd></div>
    <div><dt>Total order quantity</dt><dd>{{ $order->quantity }} {{ $order->garment === 'set' ? 'suit(s)' : 'piece(s)' }}</dd></div>
    <div><dt>Collection date</dt><dd><strong>{{ \Carbon\Carbon::parse($order->collecting_on)->format('d M Y') }}</strong></dd></div>
    <div><dt>Karigar</dt><dd>{{ optional($order->karigar)->fullname ?: 'Unassigned' }}</dd></div>
    @if($order->priority === 'emergency')<div class="urgent"><dt>Priority</dt><dd><strong>EMERGENCY</strong></dd></div>@endif
</dl>
<footer class="document-footer"><p>Find this bundle in Orders using the tracking code.</p></footer>
@endsection
