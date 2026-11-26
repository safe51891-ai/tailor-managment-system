@extends('layouts.app')
@push('breadcrumb')<h3 class="content-header-title">Customers</h3><p class="mb-0">Find a customer, save measurements, or start their next order.</p>@endpush
@push('breadcrumb-button')<a class="btn btn-primary btn-add-customer" href="{{ route('customers.create') }}"><span aria-hidden="true">+</span> Add a new customer</a>@endpush
@section('content')
<div class="tailor-workflow">

<div class="card"><div class="card-body">
<form method="get" class="workflow-search"><label for="customer-search">Search by mobile number or name</label><div class="d-flex"><input id="customer-search" class="form-control" name="search" value="{{ $search }}" placeholder="03001234567, +966512345678, or name"><button class="btn btn-primary ml-1">Search</button><a class="btn btn-outline-secondary ml-1" href="{{ route('customers') }}">Clear</a></div></form>
<div class="table-responsive"><table class="table workflow-table"><thead><tr><th>Customer</th><th>Phone</th><th>Latest measurements</th><th>Actions</th></tr></thead><tbody>
@forelse ($customers as $customer)
<tr><td><strong>{{ $customer->fullname }}</strong></td><td>{{ $customer->phone }}</td><td>@if ($customer->measurements->first()){{ $customer->measurements->first()->measured_on->format('d M Y') }} · {{ $customer->measurements->first()->unit }}@else<span class="text-muted">Not entered yet</span>@endif</td><td class="workflow-actions"><a class="btn btn-outline-primary" href="{{ route('measurements.index', $customer) }}">Measurements</a><a class="btn btn-primary" href="{{ route('orders.create', ['customer' => $customer->id]) }}">New order</a><a class="btn btn-outline-secondary" href="{{ route('customers.edit', $customer) }}">Edit</a><button type="button" class="btn btn-outline-danger deletebtn" data-id="{{ $customer->id }}" data-label="{{ $customer->fullname }} — {{ $customer->phone }}">Delete</button></td></tr>
@empty<tr><td colspan="4" class="workflow-empty">No customers found. Add a customer to begin.</td></tr>@endforelse
</tbody></table></div>{{ $customers->links('pagination::bootstrap-4') }}
</div></div>
<x-modals.delete :route="'customer.destroy'" :title="'Customer'" />
</div>
@endsection
