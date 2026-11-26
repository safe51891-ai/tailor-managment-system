@extends('layouts.app')
@push('breadcrumb')<div class="page-eyebrow">YOUR SHOP AT A GLANCE</div><h1 class="content-header-title">Shop overview</h1><p class="mb-0">{{ now()->format('l, d F Y') }} · Click a card to view its details.</p>@endpush
@push('breadcrumb-button')<a class="btn btn-primary" href="{{ route('orders.create') }}">+ New order</a>@endpush
@section('content')
<div class="tailor-workflow shop-dashboard">
<div class="workflow-actions dashboard-quick-actions"><a class="btn btn-outline-primary" href="{{ route('customers') }}">View customers</a><a class="btn btn-outline-primary" href="{{ route('income') }}#record-income">Record income</a><a class="btn btn-outline-primary" href="{{ route('expenses') }}">Record expense</a><a class="btn btn-outline-primary" href="{{ route('dues.receivable') }}">Money to receive</a><a class="btn btn-outline-primary" href="{{ route('dues.payable') }}">Money to pay</a></div>
<form method="get" class="period-filter card"><div class="card-body d-flex align-items-end flex-wrap"><div class="form-group mb-0 mr-2"><label for="report-period">Work & money report</label><select class="form-control" id="report-period" name="period"><option value="weekly" @if($period === 'weekly') selected @endif>Week</option><option value="monthly" @if($period === 'monthly') selected @endif>This month</option></select></div><button class="btn btn-primary">Show report</button><p class="mb-0 ml-2">{{ $periodStart->format('d M Y') }} – {{ $periodEnd->format('d M Y') }} · through today</p></div></form>
<div class="shop-stat-grid">
@foreach([['Due today', $dueToday, 'today', 'Today’s collection list', 'stat-teal'], ['Overdue', $overdue, 'overdue', 'Orders needing attention', 'stat-rust'], ['Ready to collect', $ready, 'ready', 'Finished and waiting', 'stat-gold'], ['Active orders', $activeOrders, 'active', 'Work currently in progress', 'stat-ink']] as $stat)
<a class="shop-stat {{ $stat[4] }}" href="{{ route('orders', $stat[2] === 'ready' ? ['status' => 'ready'] : ['due' => $stat[2]]) }}"><span class="shop-stat-label">{{ $stat[0] }}</span><strong>{{ $stat[1] }}</strong><span class="shop-stat-description">{{ $stat[3] }} <span aria-hidden="true">↗</span></span></a>
@endforeach
</div>
<div class="dashboard-money-grid">
@foreach([['Customer order balances', $orderBalance, route('orders', ['balance' => 'unpaid']), 'Unpaid amounts on saved orders', 'stat-gold'], ['Other money to receive', $receiveBalance, route('dues.receivable'), 'Other people owe the owner', 'stat-teal'], ['Money to pay', $payBalance, route('dues.payable'), 'The owner owes other people', 'stat-rust']] as $money)
<a class="shop-stat dashboard-money-card {{ $money[4] }}" href="{{ $money[2] }}"><span class="shop-stat-label">{{ $money[0] }}</span><strong>PKR {{ number_format($money[1], 2) }}</strong><span class="shop-stat-description">{{ $money[3] }} · All dates →</span></a>
@endforeach
</div>
<section class="card"><div class="card-body"><div class="shop-section-heading"><h2>Next deliveries</h2><a class="btn btn-outline-primary" href="{{ route('orders', ['due' => 'active']) }}">View all active orders</a></div><div class="table-responsive"><table class="table workflow-table"><thead><tr><th>Order / customer</th><th>Garment / karigar</th><th>Delivery</th><th>Status</th><th>Details</th></tr></thead><tbody>@forelse($attentionOrders as $item)<tr><td><strong>{{ $item->tracking_code }}</strong><div>{{ optional($item->customer)->fullname }}</div></td><td>{{ config('measurements.garments')[$item->garment] }}<div class="text-muted">{{ optional($item->karigar)->fullname ?: 'Assign a karigar' }}</div></td><td>{{ \Carbon\Carbon::parse($item->collecting_on)->format('d M Y') }}@if($item->collecting_on < $today)<div class="text-danger">Overdue</div>@endif</td><td><span class="order-status status-{{ $item->status }}">{{ config('measurements.statuses')[$item->status] }}</span>@if($item->priority === 'emergency')<span class="order-priority priority-emergency">Emergency</span>@endif</td><td><a class="btn btn-outline-primary" href="{{ route('orders.show', $item) }}">Open order</a></td></tr>@empty<tr><td colspan="5">No pending deliveries. <a href="{{ route('orders.create') }}">Create a new order</a>.</td></tr>@endforelse</tbody></table></div></div></section>
<div class="shop-dashboard-grid">
<div class="shop-chart-stack">
<section class="card"><div class="card-body"><span class="page-eyebrow">{{ $period === 'weekly' ? 'THIS WEEK' : 'THIS MONTH' }}</span><h2>Income &amp; expenses</h2><p class="text-muted">Recorded money by date, in Pakistani rupees.</p><div class="overview-chart"><canvas id="finance-chart" role="img" aria-label="Daily recorded income and expense comparison" aria-describedby="finance-chart-values"></canvas></div>
<details class="mt-2" id="finance-chart-values"><summary>View daily amounts</summary><div class="table-responsive"><table class="table workflow-table"><thead><tr><th>Date</th><th>Income (PKR)</th><th>Expenses (PKR)</th></tr></thead><tbody>@foreach($chartDays as $index => $day)<tr><td>{{ $day }}</td><td>{{ number_format($chartIncome[$index], 2) }}</td><td>{{ number_format($chartExpenses[$index], 2) }}</td></tr>@endforeach</tbody></table></div></details>
</div></section>
<section class="card"><div class="card-body"><span class="page-eyebrow">TEAM WORKLOAD</span><h2>Assigned & completed work</h2><p class="text-muted">Current unfinished orders compared with completions in the selected period.</p>@if($employeeReport->count())<div class="overview-chart"><canvas id="team-chart" role="img" aria-label="Employee assigned and completed order counts" aria-describedby="team-report-note"></canvas></div>@else<p class="measurement-help">Add employees and assign orders to start this chart. <a href="{{ route('staff') }}">Open Employees</a></p>@endif</div></section>
</div><div class="shop-dashboard-side">
<section class="card shop-finances"><div class="card-body"><span class="page-eyebrow">{{ $period === 'weekly' ? 'THIS WEEK' : 'THIS MONTH' }}</span><h2>Money at a glance</h2><dl><div><dt><a href="{{ route('income', ['period' => $period]) }}">Recorded income →</a></dt><dd>PKR {{ number_format($total_income, 2) }}</dd></div><div><dt><a href="{{ route('expenses', ['period' => $period]) }}">Recorded expenses →</a></dt><dd>PKR {{ number_format($total_expense, 2) }}</dd></div><div><dt><a href="#employee-report-details">Calculated employee pay ↓</a></dt><dd>PKR {{ number_format($employeeTotal, 2) }}</dd></div><div><dt><a href="{{ route('customers') }}">Customers →</a></dt><dd>{{ $customerCount }}</dd></div></dl><p class="text-muted">Employee pay is calculated separately. Recorded expenses are based on purchase dates; they may already include wage payments.</p></div></section></div>
</div>
<section class="card"><div class="card-body"><div class="shop-section-heading"><div><span class="page-eyebrow">KARIGAR WORK & EARNINGS</span><h2>{{ $period === 'weekly' ? 'This week’s' : 'This month’s' }} team report</h2></div><a href="{{ route('staff') }}">Manage team & rates →</a></div>
@if($unassigned)<div class="measurement-help">{{ $unassigned }} unfinished order(s) still need a karigar. <a href="{{ route('orders') }}">Assign work</a></div>@endif
<details id="employee-report-details"><summary>View employee work and pay details</summary><div class="table-responsive"><table class="table workflow-table"><thead><tr><th>Employee</th><th>Assigned unfinished<br><small>Current orders / units</small></th><th>Completed orders<br><small>Selected period</small></th><th>Completed suits</th><th>Salary accrued (PKR)</th><th>Work pay (PKR)</th><th>Total earned (PKR)</th></tr></thead><tbody>
@forelse($employeeReport as $row)<tr><td><strong>{{ $row['employee']->fullname }}</strong><div>{{ $row['employee']->trashed() ? 'Archived employee' : ($row['employee']->is_karigar ? 'Karigar' : 'Employee') }}</div></td><td>{{ $row['assigned'] }} / {{ $row['assigned_units'] }}</td><td>{{ $row['completed'] }}</td><td>{{ $row['suits'] }}</td><td>{{ number_format($row['salary'], 2) }}</td><td>{{ number_format($row['work_pay'], 2) }}</td><td><strong>{{ number_format($row['total'], 2) }}</strong></td></tr>@empty<tr><td colspan="7">Add an employee and configure their pay to start the team report.</td></tr>@endforelse
</tbody><tfoot><tr><th colspan="6">Total employee earnings through today</th><th>PKR {{ number_format($employeeTotal, 2) }}</th></tr></tfoot></table></div>
<p class="text-muted" id="team-report-note">Assigned counts show unfinished work right now. Completion is recorded once at Ready / Delivered. Salary uses monthly rate ÷ calendar days in that month. These are earned amounts, not a record of wages paid. Older completed orders without a recorded completion date are excluded.</p></details>
</div></section></div>
@endsection

@push('page-js')
<script src="{{ asset('app-assets/vendors/js/charts/chart.min.js') }}"></script>
<script>
(function () {
    var report = document.getElementById('employee-report-details');
    document.querySelectorAll('a[href="#employee-report-details"]').forEach(function (link) { link.addEventListener('click', function () { report.open = true; }); });
    if (typeof Chart === 'undefined') return;
    var animation = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 250;
    new Chart(document.getElementById('finance-chart'), {
        type: 'bar', data: { labels: @json($chartDays), datasets: [
            { label: 'Income (PKR)', backgroundColor: '#176458', data: @json($chartIncome) },
            { label: 'Expenses (PKR)', backgroundColor: '#9e3d32', data: @json($chartExpenses) }
        ] }, options: { responsive: true, maintainAspectRatio: false, animation: { duration: animation }, legend: { position: 'bottom' }, scales: { yAxes: [{ ticks: { beginAtZero: true }, scaleLabel: { display: true, labelString: 'PKR' } }], xAxes: [{ gridLines: { display: false } }] }, tooltips: { callbacks: { label: function(item, data) { return data.datasets[item.datasetIndex].label + ': ' + Number(item.yLabel).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); } } } }
    });
    var team = document.getElementById('team-chart');
    if (team) new Chart(team, { type: 'horizontalBar', data: { labels: @json($employeeReport->map(function ($row) { return $row['employee']->fullname; })->values()), datasets: [
        { label: 'Assigned unfinished', backgroundColor: '#245778', data: @json($employeeReport->pluck('assigned')->values()) },
        { label: 'Completed in period', backgroundColor: '#176458', data: @json($employeeReport->pluck('completed')->values()) }
    ] }, options: { responsive: true, maintainAspectRatio: false, animation: { duration: animation }, legend: { position: 'bottom' }, scales: { xAxes: [{ ticks: { beginAtZero: true, precision: 0 }, scaleLabel: { display: true, labelString: 'Orders' } }], yAxes: [{ gridLines: { display: false } }] } } });
})();
</script>
@endpush
