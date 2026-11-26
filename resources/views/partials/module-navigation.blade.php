@php
    $moduleLinks = [
        'employees' => ['label' => 'Employees', 'links' => ['staff' => 'Employees & pay', 'designations' => 'Employee roles']],
        'expenses' => ['label' => 'Expenses', 'links' => ['expenses' => 'Expense records', 'expense-categories' => 'Expense categories']],
        'income' => ['label' => 'Income', 'links' => ['income' => 'Income records', 'dues.receivable' => 'Money to receive', 'dues.payable' => 'Money to pay', 'income-categories' => 'Income categories']],
    ][$moduleKey];
@endphp
<nav class="shop-module-tabs" aria-label="{{ $moduleLinks['label'] }} sections">
@foreach($moduleLinks['links'] as $destination => $label)
<a class="btn {{ Request::routeIs($destination) ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route($destination) }}" @if(Request::routeIs($destination)) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav>
