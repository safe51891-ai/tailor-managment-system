<?php
namespace App\Http\Controllers;

use App\Models\Due;
use App\Models\DuePayment;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Orders;
use App\Rules\PersonName;
use App\Rules\MobileNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DueController extends Controller
{
    public function index(Request $request)
    {
        $direction = $request->routeIs('dues.payable') ? 'payable' : 'receivable';
        $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|in:open,settled,all']);
        $status = $request->query('status', 'open');
        $search = trim((string) $request->query('search', ''));
        $base = Due::where('direction', $direction);
        $total = (clone $base)->selectRaw('COALESCE(SUM(amount - paid_amount), 0) AS outstanding_total')->value('outstanding_total');
        $dues = $base->when($status !== 'all', function ($query) use ($status) {
            $query->whereColumn('amount', $status === 'settled' ? '=' : '>', 'paid_amount');
        })->when($search !== '', function ($query) use ($search) {
            $phone = MobileNumber::search($search);
            $query->where(function ($q) use ($search, $phone) { $q->where('person_name', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$phone.'%'); });
        })->orderByDesc('id')->paginate(20)->withQueryString();
        $orderBalance = Orders::selectRaw('COALESCE(SUM(amount_charged - amount_paid), 0) AS balance')->value('balance');
        return view('dues', compact('direction', 'status', 'search', 'dues', 'total', 'orderBalance') + ['title' => $direction === 'receivable' ? 'Money to receive' : 'Money to pay']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'direction' => 'required|in:receivable,payable', 'person_name' => ['required', 'string', 'max:100', new PersonName],
            'phone' => ['nullable', 'string', new MobileNumber], 'category' => 'required|in:'.implode(',', array_keys(Due::categories())),
            'description' => 'nullable|string|max:250', 'amount' => 'required|numeric|gt:0|max:999999999.99|regex:/^\d+(\.\d{1,2})?$/',
            'recorded_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'due_on' => 'nullable|date_format:Y-m-d|after_or_equal:recorded_on',
        ]);
        $data['phone'] = empty($data['phone']) ? null : MobileNumber::normalize($data['phone']);
        $due = Due::create($data);
        return redirect()->route('dues.show', $due)->with('message', 'Due recorded. Income or expense is recorded only when a payment is made.');
    }

    public function show(Due $due) { return view('due-detail', ['title' => $due->direction === 'receivable' ? 'Receive money' : 'Pay money', 'due' => $due->load('payments')]); }

    public function payment(Request $request, Due $due)
    {
        $data = $request->validate(['amount' => 'required|numeric|gt:0|max:999999999.99|regex:/^\d+(\.\d{1,2})?$/',
            'paid_on' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:'.$due->recorded_on->toDateString(), 'submission_token' => 'required|uuid']);
        DB::transaction(function () use ($data, $due) {
            $locked = Due::whereKey($due->id)->lockForUpdate()->firstOrFail();
            $existing = DuePayment::where('submission_token', $data['submission_token'])->first();
            if ($existing) {
                if ($existing->due_id === $locked->id && (string)$existing->amount === number_format($data['amount'], 2, '.', '') && $existing->paid_on->toDateString() === $data['paid_on']) return;
                throw ValidationException::withMessages(['submission_token' => 'This payment reference is already used. Reload the record before adding a new payment.']);
            }
            $cents = (int) round($data['amount'] * 100);
            if ($cents > (int) round($locked->balance * 100)) throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the remaining balance.']);
            $description = 'Due #'.$locked->id.' — '.$locked->person_name;
            $incomeId = $expenseId = null;
            if ($locked->direction === 'receivable') {
                $category = IncomeCategory::firstOrCreate(['name' => 'Dues received']);
                $incomeId = Income::create(['income_category_id' => $category->id, 'description' => $description, 'income_date' => $data['paid_on'], 'amount' => number_format($cents / 100, 2, '.', '')])->id;
            } else {
                $category = ExpenseCategory::firstOrCreate(['name' => 'Dues paid'], ['is_salary' => false]);
                $expenseId = Expense::create(['expense_category_id' => $category->id, 'description' => $description, 'purchase_date' => $data['paid_on'], 'price' => number_format($cents / 100, 2, '.', '')])->id;
            }
            $locked->payments()->create($data + ['income_id' => $incomeId, 'expense_id' => $expenseId]);
            $locked->update(['paid_amount' => number_format(((int) round($locked->paid_amount * 100) + $cents) / 100, 2, '.', '')]);
        }, 3);
        return redirect()->route('dues.show', $due)->with('message', 'Payment saved. The balance and corresponding Income / Expense record are updated.')->with('alert-type', 'success');
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $due = Due::findOrFail($request->id);
        DB::transaction(function () use ($due) {
            $locked = Due::whereKey($due->id)->lockForUpdate()->firstOrFail();
            if ($locked->payments()->exists()) throw ValidationException::withMessages(['id' => 'Dues with payments are kept for payment history.']);
            $locked->delete();
        });
        return redirect()->route($due->direction === 'receivable' ? 'dues.receivable' : 'dues.payable')->with('message', 'Due deleted.');
    }
}
