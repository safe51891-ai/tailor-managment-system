@php $shop = $shop ?? \App\Models\ShopProfile::current(); @endphp
<header class="document-heading">
@if($shop->logo_path)<img class="receipt-logo" src="{{ route('shop-profile.logo') }}" alt="{{ $shop->name }} logo">@else<div class="receipt-monogram" aria-label="Shop initials">{{ mb_substr($shop->name, 0, 1) }}</div>@endif
<div class="shop-name">{{ $shop->name }}</div>
@if($shop->phone)<p class="shop-contact">Phone: {{ $shop->phone }}</p>@endif
@if($shop->address)<p class="shop-contact">{{ $shop->address }}</p>@endif
<h1>{{ $documentHeading }}</h1></header>
