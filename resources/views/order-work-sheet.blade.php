@extends('layouts.order-print', ['documentType' => 'karigar-sheet', 'printLabel' => 'Print karigar measurement sheet', 'printHelp' => 'Keep this copy with the order or give it to the karigar for cutting and stitching.'])
@section('document')
@foreach($sheetOrders ?? collect([$order]) as $sheetOrder)
<article class="karigar-page">@include('partials.karigar-print-sheet', ['order' => $sheetOrder])</article>
@endforeach
@endsection
