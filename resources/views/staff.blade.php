@extends('layouts.app')
@push('breadcrumb')<h3 class="content-header-title">Employees</h3><p class="mb-0">Manage employees, karigar assignments, and pay rates. Set one pay method for each employee. Search your team or add an employee. Rates are in Pakistani rupees.</p>@endpush
@section('content')
<div class="tailor-workflow">
@if(!$showForm)
<div class="card"><div class="card-body"><div class="workflow-actions mb-2"><a class="btn btn-primary btn-add-customer" href="{{ route('staff', ['add' => 1]) }}"><span aria-hidden="true">+</span> Add employee</a></div>
<form method="get" action="{{ route('staff') }}"><label for="employee-search">Search employees by name, mobile number or role</label><div class="d-flex flex-wrap" style="gap:8px"><input class="form-control" id="employee-search" name="search" value="{{ $search }}" maxlength="100" placeholder="Name, 03001234567, or employee role" style="flex:1;min-width:200px"><button class="btn btn-outline-primary">Search employees</button><a class="btn btn-outline-secondary" href="{{ route('staff') }}">Clear</a></div></form></div></div>
@endif
@if($showForm)
<div class="card"><div class="card-body"><h2>{{ $employee->exists ? 'Edit '.$employee->fullname : 'Add an employee' }}</h2>
<form method="post" action="{{ route('staff') }}">@csrf @if($employee->exists) @method('PUT')<input type="hidden" name="id" value="{{ $employee->id }}">@endif
<div class="row">
<div class="col-md-6 form-group"><label for="fullname">Full name</label><input class="form-control" id="fullname" name="fullname" value="{{ old('fullname', $employee->fullname) }}" pattern="{{ \App\Rules\PersonName::HTML_PATTERN }}" maxlength="100" required></div>
<div class="col-md-6 form-group"><label for="phone">Mobile number</label><input class="form-control" id="phone" name="phone" value="{{ old('phone', $employee->phone) }}" type="tel" inputmode="tel" pattern="{{ \App\Rules\MobileNumber::HTML_PATTERN }}" maxlength="16" placeholder="03001234567 or +966512345678" required><small>Pakistan: 03 and 11 digits. International: +country code, no spaces.</small></div>
<div class="col-md-6 form-group"><label for="designation_id">Employee role (optional)</label><select class="form-control" id="designation_id" name="designation_id"><option value="">No role selected</option>@foreach($designations as $designation)<option value="{{ $designation->id }}" @if((string)old('designation_id', $employee->designation_id) === (string)$designation->id) selected @endif>{{ $designation->title }}</option>@endforeach</select><details class="mt-1"><summary>Add a new role</summary><label for="new-role" class="mt-1">New role name (optional)</label><input class="form-control" id="new-role" name="new_role" maxlength="100" value="{{ old('new_role') }}" placeholder="e.g. Cutter or Master tailor"><small>If entered, this role is assigned when you save the employee.</small></details></div>
<div class="col-md-6 form-group"><label for="is_karigar">Assignable as a karigar / کاریگر</label><select class="form-control" id="is_karigar" name="is_karigar"><option value="1" @if(old('is_karigar', $employee->exists ? $employee->is_karigar : true)) selected @endif>Yes — show in order dropdown</option><option value="0" @if(!old('is_karigar', $employee->exists ? $employee->is_karigar : true)) selected @endif>No — other employee</option></select></div>
<div class="col-md-6 form-group"><label for="employment_start">Employment start</label><input class="form-control" type="date" id="employment_start" name="employment_start" value="{{ old('employment_start', $employee->employment_start ? $employee->employment_start->toDateString() : now()->toDateString()) }}" max="{{ now()->toDateString() }}" @if($employee->exists) readonly @endif required></div>
<div class="col-md-6 form-group"><label for="address">Address (optional)</label><input class="form-control" id="address" name="address" maxlength="200" value="{{ old('address', $employee->address) }}"></div>
<div class="col-md-6 form-group"><label for="pay_method">Pay method</label><select class="form-control" id="pay_method" name="pay_method">@foreach(config('tailoring.pay_methods') as $value => $label)<option value="{{ $value }}" @if(old('pay_method', $employee->pay_method ?: 'salary') === $value) selected @endif>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-6 form-group"><label for="pay_rate" id="pay-rate-label">Monthly salary (PKR, optional)</label><input class="form-control" type="number" id="pay_rate" name="pay_rate" value="{{ old('pay_rate', $employee->exists ? $employee->pay_rate : '') }}" min="0" max="9999999.99" step="0.01"><small id="pay-help" aria-live="polite"></small></div>
</div>
<fieldset class="stitching-options"><legend>Payment account (optional)</legend><p class="text-muted">Save where the employee receives payments. These details can be added later.</p><div class="row">
<div class="col-md-6 form-group"><label for="payment_account_type">Payment through</label><select class="form-control" id="payment_account_type" name="payment_account_type"><option value="">Not provided</option>@foreach(config('tailoring.payment_accounts') as $value => $label)<option value="{{ $value }}" @if(old('payment_account_type', $employee->payment_account_type) === $value) selected @endif>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-6 form-group" data-account-field><label for="account_number" id="account-number-label">Account number / IBAN (optional)</label><input class="form-control" id="account_number" name="account_number" type="text" maxlength="34" value="{{ old('account_number', $employee->account_number) }}" autocomplete="off"><small id="account-number-help">Keep any leading zeros. Enter the account number or IBAN exactly as provided.</small></div>
<div class="col-md-6 form-group" id="bank-name-group"><label for="bank_name">Bank / provider name (optional)</label><input class="form-control" id="bank_name" name="bank_name" maxlength="100" value="{{ old('bank_name', $employee->bank_name) }}" placeholder="e.g. HBL, Meezan Bank"></div>
<div class="col-md-6 form-group" data-account-field><label for="account_title">Account holder / title (optional)</label><input class="form-control" id="account_title" name="account_title" maxlength="100" value="{{ old('account_title', $employee->account_title) }}"></div>
</div></fieldset>
<div class="measurement-help">Salary accrues by calendar day: monthly salary ÷ days in that month. Weekly and monthly reports show earnings through today. Work-based pay is earned once at Ready / Delivered. Per-suit pay applies to complete shalwar kameez sets.</div>
<div class="workflow-actions"><button class="btn btn-primary">Save</button><a class="btn btn-outline-secondary" href="{{ route('staff') }}">Cancel</a></div></form></div></div>
@endif
<div class="card"><div class="card-body"><h2>Your team</h2><div class="table-responsive"><table class="table workflow-table"><thead><tr><th>Employee</th><th>Phone</th><th>Role / assignment</th><th>Pay method / rate</th><th>Payment account</th><th>Actions</th></tr></thead><tbody>
@forelse($staff as $member)<tr><td><strong>{{ $member->fullname }}</strong><div>{{ $member->address }}</div></td><td>{{ $member->phone }}</td><td>{{ optional($member->designation)->title ?: 'No role selected' }}<div>{{ $member->is_karigar ? 'Assignable karigar' : 'Other employee' }}</div></td><td>{{ config('tailoring.pay_methods')[$member->pay_method] }}<div>{{ number_format($member->pay_rate, 2) }} {{ $member->pay_method === 'percentage' ? '%' : 'PKR' }}</div></td><td>{{ config('tailoring.payment_accounts')[$member->payment_account_type] ?? 'Not provided' }}@if($member->bank_name)<div>{{ $member->bank_name }}</div>@endif @if($member->account_number)<div>Ending {{ substr($member->account_number, -4) }}</div>@endif</td><td class="workflow-actions"><a class="btn btn-outline-primary" href="{{ route('staff', ['edit' => $member->id]) }}">Edit</a><button type="button" class="btn btn-outline-danger deletebtn" data-id="{{ $member->id }}" data-label="{{ $member->fullname }}">Delete</button></td></tr>@empty<tr><td colspan="6">{{ $search !== '' ? 'No employees match your search. Try another name or mobile number.' : 'No employees yet. Choose Add employee to build your team.' }}</td></tr>@endforelse
</tbody></table></div>{{ $staff->links('pagination::bootstrap-4') }}</div></div><x-modals.delete :route="'staff.destroy'" :title="'Employee'" />
</div>
@endsection
@push('page-js')
@if($showForm)<script>
(function(){var method=document.getElementById('pay_method'), rate=document.getElementById('pay_rate'); function update(){var percentage=method.value==='percentage', salary=method.value==='salary';rate.required=!salary;document.getElementById('pay-rate-label').textContent=percentage?'Percentage (%)':salary?'Monthly salary (PKR, optional)':method.value==='per_suit'?'Rate per complete suit (PKR)':'Rate per order (PKR)';rate.max=percentage?'100':'9999999.99';document.getElementById('pay-help').textContent=salary?'Optional: leave blank to save with zero salary. You can set it later.':percentage?'Required: based on the full order price, including any unpaid balance.':'Required: enter the agreed rate. Changing it takes effect today.';}method.addEventListener('change',update);update();})();
(function () {
    var provider = document.getElementById('payment_account_type'), number = document.getElementById('account_number');
    function updateAccount() {
        var wallet = ['easypaisa', 'jazzcash'].indexOf(provider.value) >= 0;
        var account = provider.value !== '' && provider.value !== 'cash';
        document.querySelectorAll('[data-account-field]').forEach(function(group){ group.hidden = !account; group.querySelector('input').disabled = !account; });
        var bank = ['bank','other'].indexOf(provider.value) >= 0;
        document.getElementById('bank-name-group').hidden = !bank; document.getElementById('bank_name').disabled = !bank;
        number.maxLength = wallet ? 11 : 34; number.inputMode = wallet ? 'numeric' : 'text';
        number.placeholder = wallet ? '03001234567' : 'Account number or IBAN';
        if(wallet) number.pattern = '03[0-9]{9}'; else number.removeAttribute('pattern');
        document.getElementById('account-number-label').textContent = wallet ? 'Registered wallet mobile number (optional)' : 'Account number / IBAN (optional)';
        document.getElementById('account-number-help').textContent = wallet ? 'Use the employee’s registered wallet number: 11 digits starting with 03.' : 'Keep any leading zeros. Enter the account number or IBAN exactly as provided.';
    }
    provider.addEventListener('change', updateAccount); updateAccount();
})();
</script>@endif
@endpush
