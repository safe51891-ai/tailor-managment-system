@if($order->exists)
<div class="card"><div class="card-body"><form method="get" action="{{ route('orders.edit', $order) }}"><label for="customer-picker">Customer</label><div class="d-flex"><select class="form-control" id="customer-picker" name="customer" required><option value="">Choose a customer</option>@foreach($customers as $item)<option value="{{ $item->id }}" @if((string) $customerId === (string) $item->id) selected @endif>{{ $item->fullname }} — {{ $item->phone }}</option>@endforeach</select><button class="btn btn-outline-primary ml-1">Load customer</button></div><small>Loading another customer clears unsaved order details.</small></form></div></div>
@elseif($customer)
<p class="mb-2"><strong>{{ $customer->fullname }}</strong> · {{ $customer->phone }} <a class="ml-2" href="{{ route('customers.edit', ['customer' => $customer, 'order_flow' => 1, 'receipt_order' => optional($receiptOrder ?? null)->id]) }}">Edit customer details</a> <a class="ml-2" href="{{ route('orders.create') }}">Change customer</a></p>
@else
<div class="card"><div class="card-body"><h4>1. Choose a customer</h4>
<div class="workflow-actions mb-2"><a class="btn btn-primary btn-add-customer" href="{{ route('customers.create', ['order_flow' => 1]) }}">+ Add a new customer</a><a class="btn btn-outline-primary" href="#existing-customer-search">Find an existing customer</a></div>
<p class="text-muted">New customer: save their details, then enter measurements and order details together. Existing customer: search by mobile number and load their saved record.</p>
<form id="existing-customer-search" method="get" action="{{ route('orders.create') }}">
<label for="order-customer-phone">Existing customer's mobile number</label><div class="d-flex"><input class="form-control" type="tel" inputmode="tel" id="order-customer-phone" name="phone" value="{{ ($phoneQuery ?? '') !== '' ? $phoneQuery : optional($customer)->phone }}" placeholder="03001234567 or +966512345678" maxlength="40" required><button class="btn btn-primary ml-1" type="submit">Search &amp; load customer</button></div>
<small>Enter the complete number. Pakistani +92 numbers and numbers with spaces are accepted for searching.</small>
<small class="d-block">Loading a customer clears unsaved order details. Update customer details before filling the order; measurements can be edited below.</small>
</form>
@if(($phoneQuery ?? '') !== '' && !$customers->count())<p class="measurement-help mt-2" role="status">No customer found for this mobile number. Check the number or add a new customer.</p>@endif
@if($customers->count() > 1)<form method="get" action="{{ route('orders.create') }}" class="mt-2"><input type="hidden" name="phone" value="{{ $phoneQuery }}"><label for="customer-picker">More than one customer uses this number. Choose the correct person.</label><div class="d-flex"><select class="form-control" id="customer-picker" name="customer" required><option value="">Choose a customer</option>@foreach($customers as $item)<option value="{{ $item->id }}" @if((string)$customerId === (string)$item->id) selected @endif>{{ $item->fullname }} — {{ $item->phone }}</option>@endforeach</select><button class="btn btn-primary ml-1">Load customer</button></div></form>@endif
</div></div>
@endif
