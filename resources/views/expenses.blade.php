@extends('layouts.app')
@push('breadcrumb')<h3 class="content-header-title">Shop expenses</h3><p class="mb-0">Keep track of money paid for your shop, supplies and employees.</p>@endpush
@push('breadcrumb-button')
@if(!$showForm)<a class="btn btn-primary" href="{{ route('expenses', $filters + ['add' => 1]) }}">+ Add expense</a>@endif
@endpush
@section('content')
<div class="tailor-workflow">
@if($showForm)
<section class="card"><div class="card-body"><h2>{{ $expense->exists ? 'Edit expense' : 'Add expense' }}</h2><p class="text-muted">Record money actually paid. Choose a category, enter the amount and save.</p>
<form method="post" action="{{ route('expenses', $filters) }}">@csrf
@if($expense->exists) @method('PUT')<input type="hidden" name="id" value="{{ $expense->id }}">@endif
<div class="row">
<div class="col-md-6 form-group"><label for="category">Expense category *</label><select class="form-control" id="category" name="category" required><option value="">Choose rent, bills, salaries or supplies</option>@foreach($categories as $category)<option value="{{ $category->id }}" @if((string)old('category', $expense->expense_category_id) === (string)$category->id) selected @endif>{{ $category->name }}</option>@endforeach</select></div>
<div class="col-md-6 form-group"><label for="price">Amount paid (PKR) *</label><input class="form-control" type="number" name="price" id="price" min="0.01" max="999999999.99" step="0.01" placeholder="e.g. 1500" value="{{ old('price', $expense->price) }}" required><small>Enter the actual payment, using up to two decimal places.</small></div>
<div class="col-md-6 form-group" id="salary-employee-group" hidden><label for="employee_id">Paid to employee *</label><select class="form-control" id="employee_id" name="employee_id"><option value="">Choose an employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @if((string)old('employee_id', $expense->employee_id) === (string)$employee->id) selected @endif>{{ $employee->fullname }}</option>@endforeach
@if($expense->employee && $expense->employee->trashed())<option value="{{ $expense->employee_id }}" selected>{{ $expense->employee->fullname }} (archived)</option>@endif
</select><small>Salary, advance or work pay: enter what you paid, not the calculated earnings.</small></div>
<div class="col-md-6 form-group"><label for="purchase_date">Date paid *</label><input class="form-control" type="date" id="purchase_date" name="purchase_date" value="{{ old('purchase_date', $expense->purchase_date ?: now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
<div class="col-md-6 form-group"><label for="description">Details / reference (optional)</label><input class="form-control" id="description" name="description" maxlength="100" value="{{ old('description', $expense->description) }}" placeholder="e.g. October rent, electricity bill, thread purchase"><small>Use a bill number, salary period or supply description.</small></div>
</div>
<div class="measurement-help" id="expense-save-preview" aria-live="polite">Choose a category and enter the amount paid.</div>
<p class="text-muted mt-2">Paying an existing due? <a href="{{ route('dues.payable') }}">Record the payment under Money to pay</a> so its balance updates automatically.</p>
<div class="workflow-actions"><button class="btn btn-primary" type="submit">Save expense</button><a class="btn btn-outline-secondary" href="{{ route('expenses', $filters) }}">Cancel</a></div>
</form></div></section>
@else
<div class="expense-summary-grid">
<section class="card"><div class="card-body"><span class="page-eyebrow">TOTAL PAID</span><h2>PKR {{ number_format($total, 2) }}</h2><p class="mb-0">{{ $start->format('d M Y') }} – {{ $end->format('d M Y') }} · matching records</p></div></section>
<section class="card"><div class="card-body"><span class="page-eyebrow">EMPLOYEE PAYMENTS</span><h2>PKR {{ number_format($employeeTotal, 2) }}</h2><p class="mb-0">Actual employee payments within this total</p></div></section>
<section class="card"><div class="card-body"><span class="page-eyebrow">EXPENSE RECORDS</span><h2>{{ number_format($recordCount) }}</h2><p class="mb-0">Across all pages of the selected results</p></div></section>
</div>
<section class="card"><div class="card-body"><h4>Find expenses</h4><form method="get" action="{{ route('expenses') }}">
<div class="row">
<div class="col-md-3 form-group"><label for="period">Period</label><select class="form-control" name="period" id="period"><option value="weekly" @if($period === 'weekly') selected @endif>Week</option><option value="monthly" @if($period === 'monthly') selected @endif>Month</option><option value="custom" @if($period === 'custom') selected @endif>Choose dates</option></select></div>
<div class="col-md-4 form-group"><label for="filter-category">Category</label><select class="form-control" id="filter-category" name="filter_category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @if((string)$filterCategory === (string)$category->id) selected @endif>{{ $category->name }}</option>@endforeach</select></div>
<div class="col-md-5 form-group"><label for="expense-search">Search details, category or employee</label><input class="form-control" id="expense-search" name="search" value="{{ $search }}" maxlength="100" placeholder="e.g. electricity, thread, Ali Khan"></div>
<div class="col-md-6 form-group" data-expense-date><label for="expense-from">From</label><input class="form-control" type="date" name="from" id="expense-from" value="{{ $start->toDateString() }}" max="{{ now()->toDateString() }}"></div>
<div class="col-md-6 form-group" data-expense-date><label for="expense-to">To</label><input class="form-control" type="date" name="to" id="expense-to" value="{{ $end->toDateString() }}" max="{{ now()->toDateString() }}"></div>
</div><div class="workflow-actions"><button class="btn btn-primary">Show expenses</button><a class="btn btn-outline-secondary" href="{{ route('expenses') }}">Reset</a><a class="btn btn-outline-secondary" href="{{ route('expense-categories') }}">Manage categories</a></div>
</form></div></section>
@if($breakdown->count())
<section class="card"><div class="card-body"><details><summary>Spending by category · {{ $breakdown->count() }} categories</summary><div class="expense-category-breakdown mt-2">
@foreach($breakdown as $group)
@php $categoryName = optional($categories->firstWhere('id', $group->expense_category_id))->name ?: 'Uncategorized'; $share = $total > 0 ? min(100, max(0, $group->amount_total / $total * 100)) : 0; @endphp
<div><a href="{{ route('expenses', array_replace($filters, ['filter_category' => $group->expense_category_id])) }}">{{ $categoryName }}</a><strong>PKR {{ number_format($group->amount_total, 2) }}</strong><div class="expense-share-track" aria-hidden="true"><span style="width:{{ number_format($share, 2, '.', '') }}%"></span></div></div>
@endforeach
</div></details></div></section>
@endif
<section class="card"><div class="card-body"><div class="shop-section-heading"><h2>Expense records</h2><span>{{ $expenses->total() }} matching records · newest payments first</span></div>
<div class="table-responsive"><table class="table workflow-table"><thead><tr><th>Date paid</th><th>Category / details</th><th>Paid to</th><th>Amount (PKR)</th><th>Actions</th></tr></thead><tbody>
@forelse($expenses as $item)
<tr><td>{{ \Carbon\Carbon::parse($item->purchase_date)->format('d M Y') }}<small class="d-block">Expense #{{ $item->id }}</small></td><td><strong>{{ optional($item->expenseCategory)->name ?: 'Uncategorized' }}</strong><div>{{ $item->description ?: 'No details added' }}</div></td><td>{{ optional($item->employee)->fullname ?: '—' }}</td><td><strong>{{ number_format($item->price, 2) }}</strong></td><td class="workflow-actions">
@if($item->duePayment)<a class="btn btn-outline-primary" href="{{ route('dues.show', $item->duePayment->due_id) }}">View due payment</a><small class="d-block">Linked payment · kept with due history</small>@else<a class="btn btn-outline-primary" href="{{ route('expenses', $filters + ['edit' => $item->id]) }}">Edit</a><button type="button" class="btn btn-outline-danger deletebtn" data-id="{{ $item->id }}" data-label="Expense #{{ $item->id }} — {{ optional($item->expenseCategory)->name }} — PKR {{ number_format($item->price, 2) }}">Delete</button>@endif
</td></tr>
@empty
<tr><td colspan="5"><div class="expense-empty"><h4>No expenses found</h4><p>Try another period or category, or record your first payment.</p><a class="btn btn-primary" href="{{ route('expenses', $filters + ['add' => 1]) }}">+ Add expense</a></div></td></tr>
@endforelse
</tbody></table></div>{{ $expenses->links('pagination::bootstrap-4') }}
<p class="text-muted mb-0">Only recorded payments appear here. Calculated employee earnings do not automatically become expenses.</p>
</div></section>
@endif
<x-modals.delete :route="'expense.destroy'" :title="'Expense'" />
</div>
@endsection
@push('page-js')<script>
(function () {
    var period = document.getElementById('period');
    if (period) {
        function dates() { document.querySelectorAll('[data-expense-date]').forEach(function (group) { var custom = period.value === 'custom'; group.hidden = !custom; group.querySelector('input').disabled = !custom; group.querySelector('input').required = custom; }); }
        period.addEventListener('change', dates); dates();
        document.getElementById('expense-from').addEventListener('change', function () { document.getElementById('expense-to').min = this.value; });
        document.getElementById('expense-to').min = document.getElementById('expense-from').value;
    }
    var category = document.getElementById('category');
    if (!category) return;
    var salaryCategories = @json($categories->where('is_salary', true)->pluck('id')->values());
    var employee = document.getElementById('employee_id'), price = document.getElementById('price'), preview = document.getElementById('expense-save-preview');
    function update() {
        var salary = salaryCategories.indexOf(Number(category.value)) >= 0;
        document.getElementById('salary-employee-group').hidden = !salary; employee.disabled = !salary; employee.required = salary;
        var amount = Number(price.value), label = category.value ? category.options[category.selectedIndex].text : '';
        preview.textContent = label && amount > 0 ? 'Recording PKR ' + amount.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' for ' + label + (salary && employee.value ? ' · Paid to ' + employee.options[employee.selectedIndex].text : '') : 'Choose a category and enter the amount paid.';
    }
    [category, employee, price].forEach(function (field) { field.addEventListener('change', update); field.addEventListener('input', update); }); update();
})();
</script>@endpush
