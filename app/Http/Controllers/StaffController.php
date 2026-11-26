<?php
namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Designation;
use App\Rules\PersonName;
use App\Rules\MobileNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $phoneSearch = MobileNumber::search($search);
        $staff = Staff::with('designation')->when($search !== '', function ($query) use ($search, $phoneSearch) {
            $query->where(function ($q) use ($search, $phoneSearch) {
                $q->where('fullname', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$phoneSearch}%")
                    ->orWhereHas('designation', function ($role) use ($search) { $role->where('title', 'like', "%{$search}%"); });
            });
        })->orderBy('fullname')->paginate(20)->withQueryString();
        $employee = $request->query('edit') ? Staff::findOrFail($request->query('edit')) : new Staff;
        $showForm = $employee->exists || $request->boolean('add') || session()->hasOldInput('fullname');
        return view('staff', compact('staff', 'employee', 'showForm', 'search') + ['title' => 'Employees', 'designations' => Designation::orderBy('title')->get()]);
    }

    private function data(Request $request, Staff $employee)
    {
        if (is_string($request->input('new_role'))) $request->merge(['new_role' => trim($request->input('new_role')) ?: null]);
        $data = $request->validate([
            'fullname' => ['required', 'string', 'max:100', new PersonName],
            'phone' => ['required', 'string', new MobileNumber],
            'designation_id' => 'nullable|integer|exists:designations,id',
            'new_role' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:200', 'gender' => 'nullable|in:Male,Female',
            'is_karigar' => 'required|boolean',
            'pay_method' => 'required|in:'.implode(',', array_keys(config('tailoring.pay_methods'))),
            'pay_rate' => 'nullable|required_unless:pay_method,salary|numeric|min:0|max:9999999.99',
            'payment_account_type' => 'nullable|required_with:account_number|in:'.implode(',', array_keys(config('tailoring.payment_accounts'))),
            'account_number' => ['nullable', 'prohibited_if:payment_account_type,cash', 'string', 'max:34', 'regex:/\A(?=.*[0-9])[A-Za-z0-9 -]+\z/'],
            'bank_name' => 'nullable|string|max:100',
            'account_title' => 'nullable|string|max:100',
            'employment_start' => 'required|date|before_or_equal:today',
        ]);
        $data['pay_rate'] = number_format($data['pay_rate'] ?? 0, 2, '.', '');
        if ($data['pay_method'] === 'percentage' && $data['pay_rate'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['pay_rate' => 'Percentage must be between 0 and 100.']);
        }
        if ($employee->exists && $employee->employment_start->toDateString() !== $data['employment_start']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['employment_start' => 'The start date is kept unchanged to protect salary history.']);
        }
        if ($employee->exists && !$data['is_karigar'] && $employee->orders()->whereIn('status', ['received', 'cutting', 'stitching'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['is_karigar' => 'Reassign unfinished work before removing the karigar role.']);
        }
        $type = $data['payment_account_type'] ?? null;
        $number = $data['account_number'] ?? null;
        if ($number && in_array($type, ['easypaisa', 'jazzcash'])) {
            $number = MobileNumber::normalize($number);
            if (!preg_match('/\A03[0-9]{9}\z/', $number)) throw \Illuminate\Validation\ValidationException::withMessages(['account_number' => 'For Easypaisa / JazzCash, enter the registered mobile number: 11 digits starting with 03.']);
        }
        $data['payment_account_type'] = $type;
        $data['account_number'] = $type && $type !== 'cash' ? $number : null;
        $data['bank_name'] = in_array($type, ['bank', 'other']) ? ($data['bank_name'] ?? null) : null;
        $data['account_title'] = $type && $type !== 'cash' ? ($data['account_title'] ?? null) : null;
        $data['phone'] = MobileNumber::normalize($data['phone']);
        $data['address'] = $data['address'] ?? '';
        $data['gender'] = $data['gender'] ?? '';
        $data['salary'] = $data['pay_method'] === 'salary' ? $data['pay_rate'] : 0;
        return $data;
    }

    private function save(Request $request, Staff $employee)
    {
        $data = $this->data($request, $employee);
        DB::transaction(function () use ($employee, $data) {
            if (!empty($data['new_role'])) {
                $role = Designation::whereRaw('LOWER(title) = ?', [mb_strtolower($data['new_role'])])->first();
                $data['designation_id'] = ($role ?: Designation::create(['title' => $data['new_role']]))->id;
            }
            unset($data['new_role']);
            $new = !$employee->exists;
            $changed = $new || $employee->pay_method !== $data['pay_method'] || (float) $employee->pay_rate !== (float) $data['pay_rate'];
            $employee->fill($data)->save();
            if ($changed) $employee->payTerms()->updateOrCreate(['effective_from' => $new ? $data['employment_start'] : now()->toDateString()], ['method' => $data['pay_method'], 'rate' => $data['pay_rate']]);
        });
        return redirect()->route('staff')->with('message', 'Employee saved. Pay changes apply from today; completed-order earnings stay unchanged.')->with('alert-type', 'success');
    }

    public function store(Request $request) { return $this->save($request, new Staff); }
    public function update(Request $request) { $request->validate(['id' => 'required|integer']); return $this->save($request, Staff::findOrFail($request->id)); }
    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $employee = Staff::findOrFail($request->id);
        if ($employee->orders()->whereIn('status', ['received', 'cutting', 'stitching'])->exists()) return back()->with('message', 'Reassign unfinished orders before deleting this employee.')->with('alert-type', 'warning');
        $employee->delete();
        return back()->with('message', 'Employee deleted from the team. Their work and pay history are retained.')->with('alert-type', 'success');
    }
}
