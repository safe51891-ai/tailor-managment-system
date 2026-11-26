<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ ucfirst($title) }} · {{ $shopBranding->name }}</title>
<link rel="stylesheet" href="{{ asset('app-assets/vendors/css/material-vendors.min.css') }}">
<link rel="stylesheet" href="{{ asset('app-assets/css/material.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">
@include('partials.shop-theme')
</head><body class="shop-login-body">
<main class="shop-login-layout">
<section class="shop-login-brand">@if($shopBranding->logo_path)<img class="shop-login-logo" src="{{ route('shop-profile.logo') }}" alt="{{ $shopBranding->name }} logo">@else<span class="shop-brand-mark" aria-hidden="true">{{ mb_substr($shopBranding->name, 0, 1) }}</span>@endif<span class="page-eyebrow">THE TAILOR'S DESK</span><h1>Every detail.<br>Beautifully<br>in order.</h1><p>A thoughtful workspace for the craft<br>you put into every garment.</p><div class="shop-thread-art" aria-hidden="true"><span></span><span></span><span></span></div><small>Made to measure. Managed with care.</small></section>
<section class="shop-login-panel"><div class="shop-login-form"><span class="page-eyebrow">{{ $shopBranding->name }}</span><h2>{{ $title === 'login' ? 'Welcome back.' : 'Account help' }}</h2><p class="shop-login-intro">@yield('card-subtitle')</p>
@include('partials.workflow-errors')
@yield('content')
</div></section>
</main><script src="{{ asset('assets/js/workspace.js') }}"></script></body></html>
