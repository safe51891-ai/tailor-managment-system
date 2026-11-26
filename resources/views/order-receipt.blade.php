@extends('layouts.order-print', ['documentType' => 'customer-receipt', 'printLabel' => 'Print customer receipt', 'printHelp' => 'Give this receipt to the customer. Keep the matching code on their clothes bundle.'])
@section('document')
@php
    $receiptOrders = $receiptOrders ?? collect([$order]);
    $totalPrice = $receiptOrders->sum(function ($item) { return (int) round($item->amount_charged * 100); });
    $totalPaid = $receiptOrders->sum(function ($item) { return (int) round($item->amount_paid * 100); });
@endphp
@include('partials.print-shop-heading', ['documentHeading' => 'Customer order receipt'])
<section class="tracking-block" aria-label="Order tracking code"><span>ORDER / RECEIPT NUMBER</span><strong class="tracking-code">{{ \App\Models\Orders::trackingCodeFor($order->receiptRootId()) }}</strong><p>Keep this receipt for collection.</p></section>
<dl class="document-details">
    <div><dt>Customer</dt><dd>{{ optional($order->customer)->fullname ?: 'Customer unavailable' }}</dd></div>
    <div><dt>Mobile number</dt><dd>{{ optional($order->customer)->phone ?: 'Not recorded' }}</dd></div>
    <div><dt>Date received</dt><dd>{{ \Carbon\Carbon::parse($receiptOrders->min('recieved_on'))->format('d M Y') }}</dd></div>
    <div><dt>Collection date</dt><dd><strong>{{ \Carbon\Carbon::parse($receiptOrders->max('collecting_on'))->format('d M Y') }}</strong></dd></div>
</dl>
<table class="garment-table"><thead><tr><th>Garment / bundle code</th><th>Quantity</th></tr></thead><tbody>@foreach($receiptOrders as $item)<tr><td><strong>{{ config('measurements.garments')[$item->garment] ?? 'Garment not specified' }}</strong><br><small>{{ $item->tracking_code }} · Received {{ \Carbon\Carbon::parse($item->recieved_on)->format('d M Y') }}<br>Delivery {{ \Carbon\Carbon::parse($item->collecting_on)->format('d M Y') }}</small></td><td>{{ $item->quantity }} {{ $item->garment === 'set' ? 'suit(s)' : 'piece(s)' }}</td></tr>@endforeach</tbody></table>
<dl class="money-summary">
    <div><dt>Total price</dt><dd>PKR {{ number_format($totalPrice / 100, 2) }}</dd></div>
    <div><dt>Amount paid</dt><dd>PKR {{ number_format($totalPaid / 100, 2) }}</dd></div>
    <div class="balance"><dt>Remaining balance</dt><dd>PKR {{ number_format(($totalPrice - $totalPaid) / 100, 2) }}</dd></div>
</dl>
<p class="payment-note">{{ $totalPrice - $totalPaid > 0 ? 'Please settle the remaining balance at collection.' : 'Paid in full.' }}</p>
<footer class="document-footer"><p>Bring this receipt or quote your tracking code when collecting your clothes.</p><p>Thank you for choosing {{ $shop->name }}.</p><small>Printed {{ now()->format('d M Y, h:i A') }} · Amounts reflect the current order record.</small></footer>
@endsection
