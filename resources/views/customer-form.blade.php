@extends('layouts.app')
@push('breadcrumb')<h3 class="content-header-title">{{ $title }}</h3><p class="mb-0">Only name and phone are required.</p>@endpush
@section('content')
<div class="tailor-workflow workflow-form">

<form class="card" method="post" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers') }}">
@csrf @if(request()->filled('receipt_order'))<input type="hidden" name="receipt_order" value="{{ request()->input('receipt_order') }}">@endif
@if(request()->boolean('order_flow'))<input type="hidden" name="order_flow" value="1">@endif @if ($customer->exists) @method('PUT') @endif
<div class="card-body">@include('partials.customer-fields', ['idPrefix' => ''])<div class="workflow-actions"><button class="btn btn-primary" type="submit">{{ $customer->exists ? 'Update customer record' : 'Save & enter measurements' }}</button><a class="btn btn-outline-secondary" href="{{ request()->boolean('order_flow') ? route('orders.create') : route('customers') }}">Cancel</a></div></div>
</form></div>
@endsection
