<!DOCTYPE html>
<html lang="en" data-textdirection="ltr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ ucfirst($title) }} · {{ $shopBranding->name }}</title>
<link rel="icon" href="{{ asset('favicon.ico') }}">
<link rel="stylesheet" href="{{ asset('app-assets/fonts/material-icons/material-icons.min.css') }}">
<link rel="stylesheet" href="{{ asset('app-assets/vendors/css/material-vendors.min.css') }}">
<link rel="stylesheet" href="{{ asset('app-assets/css/material.min.css') }}">
<link rel="stylesheet" href="{{ asset('app-assets/css/components.min.css') }}">
<link rel="stylesheet" href="{{ asset('app-assets/css/bootstrap-extended.min.css') }}">
<link rel="stylesheet" href="{{ asset('app-assets/css/material-colors.min.css') }}">
<link rel="stylesheet" href="{{ asset('app-assets/vendors/css/tables/datatable/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('app-assets/vendors/css/tables/extensions/buttons.dataTables.min.css') }}">
<link rel="stylesheet" href="{{ asset('app-assets/vendors/css/extensions/toastr.css') }}">
@stack('page-css')
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">
@include('partials.shop-theme')
</head>
<body class="shop-body">
<a href="#main-content" class="shop-skip-link">Skip to main content</a>
@include('includes.sidebar')
@include('includes.header')
<main class="shop-main app-content content" id="main-content" tabindex="-1">
<div class="mb-2"><a class="btn btn-outline-secondary" data-go-back href="{{ route('dashboard') }}">← Back</a></div>
<div class="shop-page-heading content-header">
<div class="shop-page-title">@if(trim($__env->yieldPushContent('breadcrumb'))) @stack('breadcrumb') @else <h1>{{ $title }}</h1> @endif</div>
<div class="shop-page-actions">@stack('breadcrumb-button')</div>
</div>
@if(session('message'))<div class="shop-feedback {{ session('alert-type') === 'warning' || session('alert-type') === 'error' ? 'shop-feedback-warning' : '' }}" role="status"><span>{{ session('message') }}</span><button type="button" data-dismiss-feedback aria-label="Dismiss notification">×</button></div>@endif
@include('partials.workflow-errors')
<div class="content-wrapper"><div class="content-body">@yield('content')</div></div>
</main>
@include('includes.footer')
<script src="{{ asset('app-assets/vendors/js/material-vendors.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/datatable/datatables.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/datatable/dataTables.buttons.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/datatable/buttons.bootstrap4.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/jszip.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/pdfmake.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/vfs_fonts.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/buttons.html5.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/buttons.print.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/tables/buttons.colVis.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/extensions/toastr.min.js') }}"></script>
<script src="{{ asset('app-assets/js/scripts/tables/datatables-extensions/datatable-button/datatable-html5.min.js') }}"></script>
@stack('page-js')
<script src="{{ asset('assets/js/workspace.js') }}?v={{ filemtime(public_path('assets/js/workspace.js')) }}"></script>
</body></html>
