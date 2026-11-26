@extends('layouts.auth')
@section('card-subtitle')Sign in to organise your shop's working day.@endsection
@section('content')
@if(session('loginError'))<div class="alert alert-danger" role="alert">{{ session('loginError') }}</div>@endif
<form method="post" action="{{ route('login') }}">@csrf
<div class="form-group"><label for="user-name">Username</label><input id="user-name" name="username" value="{{ old('username') }}" class="form-control" autocomplete="username" required autofocus></div>
<div class="form-group"><label for="user-password">Password</label><div class="shop-password-field"><input id="user-password" type="password" name="password" class="form-control" autocomplete="current-password" required><button type="button" data-toggle-password="user-password" aria-label="Show password" aria-pressed="false">Show</button></div></div>
<label class="shop-remember"><input name="remember" value="1" type="checkbox" @if(old('remember')) checked @endif> Keep me signed in on this computer</label>
<button type="submit" class="btn btn-primary btn-block">Sign in <span aria-hidden="true">→</span></button>
<p class="shop-login-help">Need access? Ask your shop administrator.</p>
</form>
@endsection
