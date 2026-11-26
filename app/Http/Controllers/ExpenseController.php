<?php
namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:weekly,monthly,custom', 'search' => 'nullable|string|max:100',
            'filter_category' => 'nullable|integer|exists:expense_categories,id',
            'from' => 'nullable|required_if:period,custom|date_format:Y-m-d|before_or_equal:today',
            'to' => 'nullable|required_if:period,custom|date_format:Y-m-d|after_or_equal:from|before_or_equal:today',
            'edit' => 'nullable|integer',
        ]);
        $categories = ExpenseCategory::orderBy('name')->get();
        $period = $request->query('period') ?: 'weekly';
        $start = $period === 'custom' ? \Carbon\Carbon::parse($request->from) : ($period === 'monthly' ? now()->startOfMonth() : now()->startOfWeek(1));
        $end = $period === 'custom' ? \Carbon\Carbon::parse($request->to) : now();
        $search = trim((string) $request->query('search', ''));
        $filterCategory = $request->query('filter_category');
        $filters = array_filter(['period' => $period, 'search' => $search, 'filter_category' => $filterCategory,
            'from' => $period === 'custom' ? $start->toDateString() : null, 'to' => $period === 'custom' ? $end->toDateString() : null], function ($value) { return $value !== null && $value !== ''; });
        $query = Expense::whereBetween('purchase_date', [$start->toDateString(), $end->toDateString()])
            ->when($filterCategory, function ($q) use ($filterCategory) { $q->where('expense_category_id', $filterCategory); })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('description', 'like', '%'.$search.'%')
                        ->orWhereHas('employee', function ($q) use ($search) { $q->where('fullname', 'like', '%'.$search.'%'); })
                        ->orWhereHas('expenseCategory', function ($q) use ($search) { $q->where('name', 'like', '%'.$search.'%'); });
                });
            });
        $total = (float) (clone $query)->selectRaw('ROUND(SUM(CAST(price AS DECIMAL(12,2))), 2) AS amount_total')->value('amount_total');
        $employeeTotal = (float) (clone $query)->whereNotNull('employee_id')->selectRaw('ROUND(SUM(CAST(price AS DECIMAL(12,2))), 2) AS amount_total')->value('amount_total');
        $recordCount = (clone $query)->count();
        $breakdown = (clone $query)->select('expense_category_id')->selectRaw('ROUND(SUM(CAST(price AS DECIMAL(12,2))), 2) AS amount_total')->groupBy('expense_category_id')->orderByDesc('amount_total')->get();
        $expenses = $query->with('expenseCategory', 'employee', 'duePayment')->orderByDesc('purchase_date')->orderByDesc('id')->paginate(20)->withQueryString();
        $expense = $request->query('edit') ? Expense::findOrFail($request->query('edit')) : new Expense;
        if ($expense->exists && $expense->duePayment) return redirect()->route('dues.show', $expense->duePayment->due_id);
        $showForm = $expense->exists || $request->boolean('add') || session()->hasOldInput('price');
        $employees = Staff::orderBy('fullname')->get();
        return view('expenses', compact('expenses', 'categories', 'period', 'start', 'end', 'total', 'employeeTotal', 'recordCount', 'breakdown', 'filters', 'search', 'filterCategory', 'showForm', 'expense', 'employees') + ['title' => 'Expenses']);
    }

    private function data(Request $request, Expense $expense)
    {
        $data = $request->validate([
            'category' => 'required|integer|exists:expense_categories,id',
            'description' => 'nullable|string|max:100',
            'purchase_date' => 'required|date|before_or_equal:today',
            'price' => ['required', 'numeric', 'gt:0', 'max:999999999.99', 'regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/'],
        ]);
        $salary = ExpenseCategory::findOrFail($data['category'])->is_salary;
        $employeeId = null;
        if ($salary) {
            $employee = $request->validate(['employee_id' => ['required', 'integer', Rule::exists('staff', 'id')->where(function ($query) use ($expense, $request) {
                if (!$expense->exists || (string) $expense->employee_id !== (string) $request->input('employee_id')) $query->whereNull('deleted_at');
            })]], ['employee_id.required' => 'Select the employee receiving this salary payment.']);
            $employeeId = $employee['employee_id'];
        }
        return ['expense_category_id' => $data['category'], 'employee_id' => $employeeId, 'description' => $data['description'] ?? null, 'purchase_date' => $data['purchase_date'], 'price' => number_format($data['price'], 2, '.', '')];
    }

    public function store(Request $request)
    {
        Expense::create($this->data($request, new Expense));
        return redirect()->route('expenses', $this->returnFilters($request))->with('message', 'Expense recorded.')->with('alert-type', 'success');
    }
    public function update(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        if (\App\Models\DuePayment::where('expense_id', $request->id)->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['id' => 'This expense belongs to a due payment and is kept with its payment history.']);
        $expense = Expense::findOrFail($request->id);
        $expense->update($this->data($request, $expense));
        return redirect()->route('expenses', $this->returnFilters($request))->with('message', 'Expense updated.')->with('alert-type', 'success');
    }
    private function returnFilters(Request $request)
    {
        return array_intersect_key($request->query(), array_flip(['period', 'search', 'filter_category', 'from', 'to'])) + ['period' => 'weekly'];
    }
    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        if (\App\Models\DuePayment::where('expense_id', $request->id)->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['id' => 'This expense belongs to a due payment and is kept with its payment history.']);
        Expense::findOrFail($request->id)->delete();
        return back()->with('message', 'Expense deleted.')->with('alert-type', 'success');
    }
}
