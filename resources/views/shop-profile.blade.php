@extends('layouts.app')
@section('content')
@php($savedTheme = $shop->theme())
<form class="shop-settings-form" method="post" enctype="multipart/form-data" action="{{ route('shop-profile.update') }}">@csrf @method('PUT')
<div class="shop-settings-grid">
<div>
<section class="card"><div class="card-body">
<span class="page-eyebrow">YOUR SHOP</span><h2>Make it yours</h2><p class="text-muted">Your name and logo appear in the app, on the sign-in screen, and on printed receipts and karigar sheets.</p>
@foreach(['name' => 'Shop name', 'phone' => 'Shop phone number', 'address' => 'Shop address / location'] as $field => $label)
<div class="form-group"><label for="shop-{{ $field }}">{{ $label }}</label><input class="form-control" id="shop-{{ $field }}" name="{{ $field }}" value="{{ old($field, $shop->$field) }}" maxlength="{{ $field === 'address' ? 500 : ($field === 'phone' ? 40 : 100) }}" @if($field === 'name') required @endif></div>
@endforeach
<div class="form-group"><label for="shop-logo">Shop logo / picture</label><input class="form-control" type="file" id="shop-logo" name="logo" accept="image/png,image/jpeg,image/webp"><small class="text-muted">PNG, JPG or WebP; up to 2 MB. A square image works best.</small></div>
@if($shop->logo_path)<img class="shop-settings-current-logo" src="{{ route('shop-profile.logo') }}" alt="Current shop logo"><label class="d-block"><input type="checkbox" id="remove-logo" name="remove_logo" value="1"> Remove current logo</label>@endif
</div></section>
<section class="card"><div class="card-body">
<span class="page-eyebrow">APPEARANCE</span><h2>Choose your colors</h2><p class="text-muted">Changes apply after you save. Printed copies keep their clear, ink-friendly layout.</p>
<div class="shop-color-fields">
@foreach(['primary_color' => 'Buttons & accents', 'sidebar_color' => 'Navigation', 'background_color' => 'App background'] as $field => $label)
<div><label for="shop-{{ $field }}">{{ $label }}</label><input class="shop-color-input" type="color" id="shop-{{ $field }}" name="{{ $field }}" value="{{ old($field, $savedTheme[$field]) }}"><small class="text-muted" data-color-value="{{ $field }}">{{ old($field, $savedTheme[$field]) }}</small></div>
@endforeach
</div>
<button class="btn btn-outline-secondary mt-2" type="button" id="shop-default-colors">Use default colors</button>
</div></section>
<div class="workflow-actions"><button class="btn btn-primary" type="submit">Save shop settings</button><a class="btn btn-outline-secondary" href="{{ route('user-profile') }}">Change your password</a></div>
</div>
<aside><section class="card shop-settings-preview"><div class="card-body"><span class="page-eyebrow">PREVIEW</span><h3>Your workspace</h3><p class="text-muted">A quick look before you save.</p>
<div id="brand-preview" style="--preview-primary:{{ $savedTheme['primary_color'] }};--preview-sidebar:{{ $savedTheme['sidebar_color'] }};--preview-background:{{ $savedTheme['background_color'] }};--preview-primary-text:{{ $savedTheme['primary_text'] }};--preview-sidebar-text:{{ $savedTheme['sidebar_text'] }}">
<div class="brand-preview-nav"><img id="preview-logo" class="brand-preview-logo" @if($shop->logo_path) src="{{ route('shop-profile.logo') }}" @else hidden @endif alt="Logo preview"><span id="preview-name">{{ old('name', $shop->name) }}</span></div>
<div class="brand-preview-main"><div class="brand-preview-card"><strong>Ready for a new day</strong><p>Customers, measurements and orders.</p><span class="brand-preview-button">Create order</span></div></div>
</div><small class="text-muted d-block mt-2" id="brand-preview-status" role="status">Your changes are saved only when you select Save shop settings.</small>
</div></section><section class="card" id="shop-license-card" hidden><div class="card-body"><span class="page-eyebrow">ACTIVATION</span><h3>App license</h3><p id="shop-license-status"></p><small id="shop-license-code" style="overflow-wrap:anywhere"></small></div></section></aside>
</div></form>
@endsection
@push('page-js')<script src="{{ asset('assets/js/shop-settings.js') }}"></script>@endpush
