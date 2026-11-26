@extends('layouts.app')
@push('breadcrumb')<h3 class="content-header-title">Print order copies</h3><p class="mb-0">{{ optional($order->customer)->fullname ?: 'Customer unavailable' }} · Receipt {{ \App\Models\Orders::trackingCodeFor($order->receiptRootId()) }}</p>@endpush
@section('content')
<div class="tailor-workflow">
<div class="workflow-steps"><span>1. Customer</span><span>2. Garments, measurements & order</span><strong>3. Print copies</strong></div>
<div class="row">
<div class="col-md-6"><section class="card h-100"><div class="card-body"><h4>Customer copy</h4><p>One receipt for all garments: customer name, quantities, order number, received and delivery dates, price, paid amount and balance, with your shop details.</p><a class="btn btn-primary" href="{{ route('orders.receipt', ['order' => $order, 'print_actions' => 1]) }}">Print for customer</a></div></section></div>
<div class="col-md-6"><section class="card h-100"><div class="card-body"><h4>Karigar / shop copy</h4><p>Measurements, customer name, assigned karigar, tracking code and received/delivery dates. A suit prints Kameez and Shalwar together. Other garments print on separate pages.</p><a class="btn btn-primary" href="{{ route('orders.work-sheet', ['order' => $order, 'all' => 1, 'print_actions' => 1]) }}">Print for karigar</a></div></section></div>
</div>
<section class="card mt-2"><div class="card-body"><h4>Garments saved in this receipt</h4><div class="table-responsive"><table class="table"><thead><tr><th>Tracking code</th><th>Garment</th><th>Quantity</th><th>Karigar</th><th>Received</th><th>Delivery</th></tr></thead><tbody>
@foreach($receiptOrders as $item)
<tr><td>{{ $item->tracking_code }}</td><td>{{ config('measurements.garments')[$item->garment] ?? 'Garment' }}</td><td>{{ $item->quantity }}</td><td>{{ optional($item->karigar)->fullname ?: 'Unassigned — assign before handing over' }}</td><td>{{ $item->recieved_on }}</td><td>{{ $item->collecting_on }}</td></tr>
@endforeach
</tbody></table></div><div class="workflow-actions"><a class="btn btn-outline-primary" href="{{ route('orders.show', $order) }}">View order / assign karigar</a><a class="btn btn-outline-secondary" href="{{ route('orders') }}">Done — back to orders</a><a class="btn btn-outline-primary" href="{{ route('orders.create') }}">Create next order</a></div></div></section>
</div>
@endsection
