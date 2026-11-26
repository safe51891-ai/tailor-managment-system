<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $order->tracking_code }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/order-print.css') }}?v={{ filemtime(public_path('assets/css/order-print.css')) }}">
</head>
<body class="{{ $documentType }}">
    <nav class="print-toolbar" aria-label="Print actions">
        @if(request()->boolean('print_actions'))
        <a href="{{ route('orders.print-options', $order) }}">← Back to print copies</a>
        @else
        <a href="{{ route('orders.show', $order) }}">← Back to order</a>
        @endif
        <button type="button" onclick="window.print()">{{ $printLabel }}</button>
    </nav>
    <p class="print-help">{{ $printHelp }} Choose your paper size and printer in the print dialog.</p>
    <main class="print-document">@yield('document')</main>
</body>
</html>
