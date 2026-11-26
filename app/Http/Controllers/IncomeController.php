<?php
namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\IncomeCategory;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $categories = IncomeCategory::orderBy('name')->get();
        $period = $request->query('period') === 'monthly' ? 'monthly' : 'weekly';
        $start = $period === 'monthly' ? now()->startOfMonth() : now()->startOfWeek(1);
        $incomes = Income::with('incomeCategory', 'duePayment')->whereBetween('income_date', [$start->toDateString(), now()->toDateString()])->orderByDesc('income_date')->get();
        $total = $incomes->sum('amount');
        $income = $request->query('edit') ? Income::findOrFail($request->query('edit')) : new Income;
        return view('income', compact('categories', 'period', 'start', 'incomes', 'total', 'income') + ['title' => 'Income']);
    }
    private function data(Request $request)
    {
        $data = $request->validate(['category' => 'required|integer|exists:income_categories,id', 'description' => 'nullable|string|max:100', 'income_date' => 'required|date|before_or_equal:today', 'amount' => 'required|numeric|min:0|max:999999999.99']);
        return ['income_category_id' => $data['category'], 'description' => $data['description'] ?? null, 'income_date' => $data['income_date'], 'amount' => number_format($data['amount'], 2, '.', '')];
    }
    public function store(Request $request)
    {
        Income::create($this->data($request));
        return redirect()->route('income', ['period' => $request->query('period', 'weekly')])->with('message', 'Income recorded.')->with('alert-type', 'success');
    }
    public function update(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        if (\App\Models\DuePayment::where('income_id', $request->id)->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['id' => 'This income belongs to a due payment and is kept with its payment history.']);
        Income::findOrFail($request->id)->update($this->data($request));
        return redirect()->route('income', ['period' => $request->query('period', 'weekly')])->with('message', 'Income updated.')->with('alert-type', 'success');
    }
    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        if (\App\Models\DuePayment::where('income_id', $request->id)->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['id' => 'This income belongs to a due payment and is kept with its payment history.']);
        Income::findOrFail($request->id)->delete();
        return back()->with('message', 'Income deleted.')->with('alert-type', 'success');
    }
}
