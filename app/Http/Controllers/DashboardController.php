<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Orders;
use App\Services\EmployeePay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->query('period') === 'monthly' ? 'monthly' : 'weekly';
        $start = $period === 'monthly' ? now()->startOfMonth() : now()->startOfWeek(1);
        $end = now()->endOfDay();
        $employeeReport = app(EmployeePay::class)->report($start, $end);
        $incomeByDay = DB::table('incomes')->whereNull('deleted_at')->whereBetween('income_date', [$start->toDateString(), $end->toDateString()])->selectRaw('income_date, SUM(amount) AS total')->groupBy('income_date')->pluck('total', 'income_date');
        $expenseByDay = DB::table('expenses')->whereBetween('purchase_date', [$start->toDateString(), $end->toDateString()])->selectRaw('purchase_date, SUM(price) AS total')->groupBy('purchase_date')->pluck('total', 'purchase_date');
        $chartDays = []; $chartIncome = []; $chartExpenses = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $chartDays[] = $day->format('d M');
            $chartIncome[] = (float) $incomeByDay->get($day->toDateString(), 0);
            $chartExpenses[] = (float) $expenseByDay->get($day->toDateString(), 0);
        }
        $today = now()->toDateString();
        $active = Orders::where('status', '!=', 'delivered');
        return view('dashboard', [
            'period' => $period, 'periodStart' => $start, 'periodEnd' => $end, 'employeeReport' => $employeeReport, 'employeeTotal' => $employeeReport->sum('total'), 'unassigned' => Orders::whereNull('karigar_id')->whereIn('status', ['received', 'cutting', 'stitching'])->count(),
            'chartDays' => $chartDays, 'chartIncome' => $chartIncome, 'chartExpenses' => $chartExpenses,
            'title' => 'Overview', 'today' => $today,
            'orderBalance' => Orders::selectRaw('COALESCE(SUM(amount_charged - amount_paid), 0) AS balance')->value('balance'),
            'receiveBalance' => \App\Models\Due::where('direction', 'receivable')->selectRaw('COALESCE(SUM(amount - paid_amount), 0) AS outstanding_total')->value('outstanding_total'),
            'payBalance' => \App\Models\Due::where('direction', 'payable')->selectRaw('COALESCE(SUM(amount - paid_amount), 0) AS outstanding_total')->value('outstanding_total'),
            'attentionOrders' => (clone $active)->with('customer', 'karigar')->orderBy('collecting_on')->limit(5)->get(),
            'dueToday' => (clone $active)->whereDate('collecting_on', $today)->count(),
            'overdue' => (clone $active)->whereDate('collecting_on', '<', $today)->count(),
            'ready' => Orders::where('status', 'ready')->count(),
            'activeOrders' => (clone $active)->count(), 'customerCount' => Customer::count(),
            'total_income' => DB::table('incomes')->whereNull('deleted_at')->whereBetween('income_date', [$start->toDateString(), $end->toDateString()])->sum('amount'),
            'total_expense' => DB::table('expenses')->whereBetween('purchase_date', [$start->toDateString(), $end->toDateString()])->sum('price'),
        ]);
    }
}
