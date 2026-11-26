<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Rules\PersonName;
use App\Rules\MobileNumber;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $phoneSearch = MobileNumber::search($search);
        $customers = Customer::with('measurements')->when($search !== '', function ($query) use ($search, $phoneSearch) {
            $query->where(function ($q) use ($search, $phoneSearch) {
                $q->where('fullname', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$phoneSearch}%");
            });
        })->orderBy('fullname')->paginate(20)->withQueryString();
        return view('customers', ['title' => 'Customers', 'customers' => $customers, 'search' => $search]);
    }

    public function create()
    {
        return view('customer-form', ['title' => 'Add customer', 'customer' => new Customer]);
    }

    public function edit(Customer $customer)
    {
        return view('customer-form', ['title' => 'Edit customer', 'customer' => $customer]);
    }

    protected function data(Request $request, Customer $customer = null)
    {
        $data = $request->validate([
            'fullname' => ['required', 'string', 'max:100', new PersonName],
            'phone' => ['required', 'string', new MobileNumber],
            'address' => 'nullable|string|max:200', 'city' => 'nullable|string|max:200',
            'email' => 'nullable|email|max:255', 'comment' => 'nullable|string|max:1000',
            'gender' => 'nullable|in:Male,Female,Other',
        ], [

            'phone.string' => 'Enter a mobile number as text, including any country code.',
        ], ['fullname' => 'full name', 'phone' => 'phone number']);
        $data['phone'] = MobileNumber::normalize($data['phone']);
        foreach (['address', 'city', 'email', 'comment', 'gender'] as $field) {
            $data[$field] = $data[$field] ?? ($customer && in_array($field, ['city', 'comment', 'email']) ? $customer->$field : '');
        }
        return $data;
    }

    public function store(Request $request)
    {
        $customer = Customer::create($this->data($request));
        return ($request->boolean('order_flow') ? redirect()->route('orders.create', ['customer' => $customer]) : redirect()->route('measurements.index', ['customer' => $customer]))->with('message', 'Customer saved. Enter garment measurements below.')->with('alert-type', 'success');
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->data($request, $customer));
        return ($request->boolean('order_flow') ? redirect()->route('orders.create', ['customer' => $customer, 'receipt_order' => $request->input('receipt_order')]) : redirect()->route('customers'))
            ->with('message', 'Customer record updated.')->with('alert-type', 'success');
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $customer = Customer::findOrFail($request->id);
        if ($customer->orders()->withTrashed()->exists()) {
            return back()->with('message', 'This customer has orders and cannot be deleted.')->with('alert-type', 'warning');
        }
        $customer->delete();
        return back()->with('message', 'Customer deleted.')->with('alert-type', 'success');
    }
}
