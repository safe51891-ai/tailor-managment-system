<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Orders;
use App\Models\Staff;
use App\Services\EmployeePay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Rules\PersonName;
use App\Rules\MobileNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrdersController extends Controller
{
    public function index(Request $request)
    {
        $priority = $request->query('priority', '');
        $status = $request->query('status', '');
        $search = trim((string) $request->query('search', ''));
        $phoneSearch = MobileNumber::search($search);
        $due = $request->query('due', '');
        $orders = Orders::with('customer', 'karigar')->when($request->query('balance') === 'unpaid', function ($q) { $q->whereRaw('CAST(amount_charged AS DECIMAL(12,2)) > CAST(amount_paid AS DECIMAL(12,2))'); })->when(in_array($priority, ['normal', 'emergency']), function ($q) use ($priority) { $q->where('priority', $priority); })->when(in_array($due, ['today', 'overdue', 'active']), function ($q) use ($due) {
            $q->where('status', '!=', 'delivered');
            if ($due === 'today') $q->whereDate('collecting_on', now()->toDateString());
            if ($due === 'overdue') $q->whereDate('collecting_on', '<', now()->toDateString());
        })->when(array_key_exists($status, config('measurements.statuses')), function ($q) use ($status) {
            $q->where('status', $status);
        })->when($search !== '', function ($q) use ($search, $phoneSearch) {
            $q->where(function ($query) use ($search, $phoneSearch) {
                $query->whereHas('customer', function ($customer) use ($search, $phoneSearch) {
                    $customer->where('fullname', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$phoneSearch}%");
                })->orWhere('tracking_code', strtoupper($search));
                // Keep older short references (TS-12) and numeric order IDs usable.
                if (preg_match('/^(?:TS-)?([0-9]+)$/i', $search, $matches)) {
                    $reference = ltrim($matches[1], '0') ?: '0';
                    $query->orWhere('id', $reference)->orWhere('receipt_group_id', $reference);
                }
            });
        })->orderBy('collecting_on')->paginate(20)->withQueryString();
        return view('orders', compact('orders', 'status', 'search', 'due', 'priority') + ['title' => 'Orders']);
    }

    protected function form(Orders $order, Request $request)
    {
        $request->validate(['phone' => 'nullable|string|max:40', 'receipt_order' => 'nullable|integer']);
        $phoneQuery = trim((string) $request->query('phone', ''));
        $customers = $order->exists ? Customer::orderBy('fullname')->get() :
            ($phoneQuery !== '' ? Customer::where('phone', MobileNumber::search($phoneQuery))->orderBy('fullname')->get() : collect());
        $customerId = old('customer', $request->query('customer', $order->customer_id));
        if (!$customerId && $phoneQuery !== '' && $customers->count() === 1) $customerId = $customers->first()->id;
        $customer = $phoneQuery !== '' || $order->exists ? $customers->firstWhere('id', $customerId) : Customer::find($customerId);
        $measurements = $customer ? $customer->measurements()->get() : collect();
        $karigars = Staff::where('is_karigar', true)->orderBy('fullname')->get();
        $receiptOrder = null;
        $receiptOrderId = old('receipt_order', $request->query('receipt_order'));
        if (!$order->exists && $receiptOrderId) {
            $receiptOrder = Orders::findOrFail($receiptOrderId);
            abort_unless($customer && (int) $receiptOrder->customer_id === (int) $customer->id, 422);
        }
        return view('order-form', compact('receiptOrder', 'phoneQuery', 'karigars', 'order', 'customers', 'customerId', 'customer', 'measurements') + ['title' => $order->exists ? 'Edit order' : 'New order']);
    }

    public function create(Request $request) { return $this->form(new Orders, $request); }
    public function edit(Request $request, Orders $order) { return $this->form($order, $request); }
    public function show(Orders $order) { return view('order-detail', ['title' => 'Order '.$order->tracking_code, 'order' => $order->load('customer', 'karigar'), 'receiptOrders' => $order->receiptOrders()]); }

    public function printOptions(Orders $order)
    {
        return response()->view('order-print-options', [
            'title' => 'Print order copies', 'order' => $order->load('customer'), 'receiptOrders' => $order->receiptOrders(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function receipt(Orders $order)
    {
        return response()->view('order-receipt', ['title' => 'Customer receipt', 'order' => $order->load('customer'), 'receiptOrders' => $order->receiptOrders(), 'shop' => \App\Models\ShopProfile::current()])
            ->header('Cache-Control', 'no-store, private');
    }

    public function tag(Orders $order)
    {
        return response()->view('order-tag', ['title' => 'Clothes tag', 'order' => $order->load('customer', 'karigar')])
            ->header('Cache-Control', 'no-store, private');
    }

    public function workSheet(Request $request, Orders $order)
    {
        return response()->view('order-work-sheet', ['title' => 'Karigar measurement sheet', 'order' => $order->load('customer', 'karigar'), 'sheetOrders' => $request->boolean('all') ? $order->receiptOrders() : collect([$order]), 'shop' => \App\Models\ShopProfile::current()])
            ->header('Cache-Control', 'no-store, private');
    }

    protected function data(Request $request, Orders $order)
    {
        $dates = $request->only(['recieved_on', 'collecting_on']);
        $validDates = \Illuminate\Support\Facades\Validator::make($dates, ['recieved_on' => 'required|date', 'collecting_on' => 'required|date'])->passes();
        $received = $validDates ? \Carbon\Carbon::parse($dates['recieved_on'])->toDateString() : '';
        $delivery = $validDates ? \Carbon\Carbon::parse($dates['collecting_on'])->toDateString() : '';
        $start = $order->start_date ? $order->start_date->toDateString() : $received;
        if ($start < $received || $start > $delivery) $start = $received;
        $end = $order->end_date ? $order->end_date->toDateString() : $delivery;
        if ($end < $start || $end > $delivery) $end = $delivery;
        // Existing integrations can omit these fields; keep saved values on edits.
        $request->merge([
            'priority' => $request->input('priority', $order->priority ?: 'normal'),
            'start_date' => $request->input('start_date', $start),
            'end_date' => $request->input('end_date', $end),
        ]);
        $data = $request->validate([
            'priority' => 'required|in:normal,emergency',
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:recieved_on',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date|before_or_equal:collecting_on',
            'customer' => 'required|integer|exists:customers,id',
            'description' => 'nullable|string|max:200', 'recieved_on' => 'required|date',
            'receiver' => ['required', 'string', 'max:100', new PersonName],
            'amount_charged' => 'required|numeric|min:0|max:999999999.99',
            'amount_paid' => 'required|numeric|min:0|lte:amount_charged',
            'collecting_on' => 'required|date|after_or_equal:recieved_on',
            'garment' => 'required|in:'.implode(',', array_keys(config('measurements.garments'))),
            'status' => 'required|in:'.implode(',', array_keys(config('measurements.statuses'))),
            'measurement_id' => 'nullable|integer',
            'karigar_id' => ['nullable', 'integer', Rule::exists('staff', 'id')->where(function ($query) use ($order, $request) { if (!$order->completed_at || (string) $request->input('karigar_id') !== (string) $order->karigar_id) $query->whereNull('deleted_at')->where('is_karigar', true); })],
            'quantity' => 'sometimes|required|integer|min:1|max:999',
            'styles' => 'nullable|array',
            'styles.daman' => 'nullable|in:'.implode(',', array_keys(config('tailoring.styles.daman.options'))),
            'styles.collar' => 'nullable|in:'.implode(',', array_keys(config('tailoring.styles.collar.options'))),
            'styles.cuff' => 'nullable|in:'.implode(',', array_keys(config('tailoring.styles.cuff.options'))),
            'styles.shalwar' => 'nullable|in:'.implode(',', array_keys(config('tailoring.styles.shalwar.options'))),
            'styles.instructions' => 'nullable|string|max:1000',
        ], [], ['receiver' => 'received by name', 'amount_charged' => 'total price', 'amount_paid' => 'amount paid', 'collecting_on' => 'delivery date', 'recieved_on' => 'date received', 'start_date' => 'work start date', 'end_date' => 'work end date']);
        $snapshot = $order->measurement_snapshot;
        $measurementId = $order->customer_measurement_id;
        if (!empty($data['measurement_id'])) {
            $measurement = Customer::findOrFail($data['customer'])->measurements()->find($data['measurement_id']);
            if (!$measurement) {
                throw ValidationException::withMessages(['measurement_id' => 'Choose measurements belonging to this customer.']);
            }
            $snapshot = $measurement->snapshot();
            $measurementId = $measurement->id;
        } elseif (!$order->exists || (int) $order->customer_id !== (int) $data['customer'] || !$snapshot) {
            throw ValidationException::withMessages(['measurement_id' => 'Save and select customer measurements before creating this order.']);
        }
        $labels = [];
        foreach (config('measurements.sections') as $fields) {
            $labels += $fields;
        }
        foreach (config('measurements.required_for_order')[$data['garment']] as $field) {
            if (empty($snapshot['values'][$field])) {
                throw ValidationException::withMessages(['measurement_id' => config('measurements.garments')[$data['garment']].' needs '.strtolower($labels[$field]).'. Update or choose matching customer measurements.']);
            }
        }
        $styles = array_intersect_key($data['styles'] ?? [], array_flip(['daman', 'collar', 'cuff', 'shalwar', 'instructions']));
        if (!$request->has('styles.instructions') && isset($order->stitching_styles['instructions'])) {
            $styles['instructions'] = $order->stitching_styles['instructions'];
        }
        // Only retain options relevant to this garment.
        if (!in_array($data['garment'], ['set', 'kameez'])) unset($styles['daman']);
        if (!in_array($data['garment'], ['set', 'shalwar'])) unset($styles['shalwar']);
        if (in_array($data['garment'], ['shalwar', 'pant'])) unset($styles['collar'], $styles['cuff']);
        if ($data['garment'] === 'waistcoat') unset($styles['cuff']);
        if (in_array('custom', $styles, true) && empty($styles['instructions'])) {
            throw ValidationException::withMessages(['styles.instructions' => 'Describe the custom stitching style for the karigar.']);
        }
        $result = [
            'priority' => $data['priority'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date'],
            'customer_id' => $data['customer'], 'description' => $request->has('description') ? ($data['description'] ?? null) : $order->description,
            'recieved_on' => $data['recieved_on'], 'recieved_by' => $data['receiver'],
            'amount_charged' => number_format($data['amount_charged'], 2, '.', ''),
            'amount_paid' => number_format($data['amount_paid'], 2, '.', ''),
            'collecting_on' => $data['collecting_on'], 'garment' => $data['garment'], 'status' => $data['status'],
            'customer_measurement_id' => $measurementId, 'measurement_snapshot' => $snapshot,
            'karigar_id' => $data['karigar_id'] ?? null, 'quantity' => $data['quantity'] ?? $order->quantity ?? 1,
            'stitching_styles' => $styles,
        ];
        if ($order->completed_at) {
            foreach (['karigar_id', 'quantity', 'garment', 'amount_charged', 'customer_id'] as $field) {
                if ((string) $result[$field] !== (string) $order->$field) {
                    throw ValidationException::withMessages([$field === 'customer_id' ? 'customer' : $field => 'Completed work has saved earnings. This detail cannot be changed.']);
                }
            }
            if (!in_array($result['status'], ['ready', 'delivered'])) {
                throw ValidationException::withMessages(['status' => 'Completed work can only be Ready or Delivered to preserve payroll history.']);
            }
        } elseif (in_array($result['status'], ['ready', 'delivered']) && (!$order->exists || !in_array($order->status, ['ready', 'delivered']))) {
            if (!$result['karigar_id']) throw ValidationException::withMessages(['karigar_id' => 'Assign a karigar before marking work Ready or Delivered.']);
            $employee = Staff::findOrFail($result['karigar_id']);
            $result += app(EmployeePay::class)->completion($employee, $result);
            $result['completed_at'] = now();
        }
        if ($order->exists && (int) $result['customer_id'] !== (int) $order->customer_id
            && ($order->receipt_group_id || Orders::where('receipt_group_id', $order->id)->exists())) {
            throw ValidationException::withMessages(['customer' => 'Garments on one receipt must belong to the same customer.']);
        }
        return $result;
    }

    protected function saveInlineMeasurements(Request $request)
    {
        if (!$request->has('measurement_values')) return;
        $request->validate([
            'customer' => 'required|integer|exists:customers,id',
            'garment' => 'required|in:'.implode(',', array_keys(config('measurements.garments'))),
            'measurement_values' => 'required|array',
            'measurement_unit' => 'required|in:inches,cm',
            'measurement_convention' => 'required|in:full,half',
        ]);
        $keys = []; $rules = []; $attributes = [];
        $required = config('measurements.required_for_order')[$request->garment];
        foreach (config('measurements.order_sections')[$request->garment] as $section) {
            foreach (config('measurements.sections')[$section] as $key => $label) {
                $keys[] = $key;
                $rules['measurement_values.'.$key] = (in_array($key, $required) ? 'required' : 'nullable').'|numeric|gt:0|max:300';
                $attributes['measurement_values.'.$key] = $section.' '.strtolower($label);
            }
        }
        $validated = $request->validate($rules, [], $attributes);
        $values = array_filter(array_intersect_key($validated['measurement_values'], array_flip($keys)), function ($value) { return $value !== null && $value !== ''; });
        $customer = Customer::whereKey($request->customer)->lockForUpdate()->firstOrFail();
        $latest = $customer->measurements()->first();
        $compatible = $latest && $latest->unit === $request->measurement_unit && $latest->convention === $request->measurement_convention;
        if ($compatible) $values = array_diff_key($latest->values, array_flip($keys)) + $values;
        if ($compatible && $latest->values == $values) {
            $measurement = $latest;
        } else {
            $measurement = $customer->measurements()->create([
                'values' => $values, 'unit' => $request->measurement_unit, 'convention' => $request->measurement_convention,
                'measured_on' => now()->toDateString(), 'taken_by' => auth()->user()->name,
                'fit' => $compatible ? $latest->fit : 'regular', 'notes' => $compatible ? $latest->notes : null,
            ]);
        }
        $request->merge(['measurement_id' => $measurement->id]);
    }

    public function store(Request $request)
    {
        $order = DB::transaction(function () use ($request) {
            $request->validate([
                'receipt_order' => 'nullable|integer', 'items' => 'nullable|array|max:19',
                'items.*' => 'required|array',
                'items.*.garment' => 'required|in:'.implode(',', array_keys(config('measurements.garments'))),
                'items.*.quantity' => 'required|integer|min:1|max:999',
                'items.*.amount_charged' => 'required|numeric|min:0|max:999999999.99',
                'items.*.amount_paid' => 'required|numeric|min:0',
                'items.*.measurement_id' => 'nullable|integer',
                'items.*.measurement_values' => 'sometimes|required|array',
                'items.*.karigar_id' => 'nullable|integer',
                'items.*.description' => 'nullable|string|max:200',
                'items.*.styles' => 'nullable|array',
            ]);
            $this->saveInlineMeasurements($request);
            $data = $this->data($request, new Orders);
            if ($request->filled('receipt_order')) {
                $parent = Orders::whereKey($request->receipt_order)->lockForUpdate()->first();
                if (!$parent || (int) $parent->customer_id !== (int) $data['customer_id']) {
                    throw ValidationException::withMessages(['receipt_order' => 'Choose an existing order for the same customer.']);
                }
                $data['receipt_group_id'] = $parent->receiptRootId();
            }
            $first = Orders::create($data);
            foreach ($request->input('items', []) ?: [] as $index => $item) {
                // Customer identity and booking dates are shared, never overridden by an item.
                $item = array_intersect_key($item, array_flip(['garment', 'quantity', 'amount_charged', 'amount_paid', 'measurement_id', 'measurement_values', 'karigar_id', 'description', 'styles']));
                $item += ['karigar_id' => null, 'description' => null, 'styles' => []];
                $shared = $request->except(['measurement_values', 'measurement_id']);
                $itemRequest = $request->duplicate(null, array_replace($shared, $item));
                try {
                    $this->saveInlineMeasurements($itemRequest);
                    $itemData = $this->data($itemRequest, new Orders);
                } catch (ValidationException $exception) {
                    $errors = [];
                    foreach ($exception->errors() as $field => $messages) {
                        $errors['items.'.$index.'.'.$field] = array_map(function ($message) use ($index) {
                            return 'Additional garment '.($index + 1).': '.$message;
                        }, $messages);
                    }
                    throw ValidationException::withMessages($errors);
                }
                $itemData['receipt_group_id'] = $first->receiptRootId();
                Orders::create($itemData);
            }
            return $first;
        });
        return redirect()->route('orders.print-options', $order)->with('message', 'Order and measurements saved. Choose the copies to print below.')->with('alert-type', 'success');
    }

    public function update(Request $request, Orders $order)
    {
        DB::transaction(function () use ($request, $order) {
            $locked = Orders::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->update($this->data($request, $locked));
        });
        return redirect()->route('orders.show', $order)->with('message', 'Order updated.')->with('alert-type', 'success');
    }

    public function destroy(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $order = Orders::findOrFail($request->id);
        if ($order->completed_at) return back()->with('message', 'Completed orders are kept for earnings history.')->with('alert-type', 'warning');
        $order->delete();
        return back()->with('message', 'Order deleted.')->with('alert-type', 'success');
    }
}
