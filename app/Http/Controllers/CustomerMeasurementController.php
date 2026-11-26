<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Rules\PersonName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerMeasurementController extends Controller
{
    public function index(Customer $customer)
    {
        $measurements = $customer->measurements()->get();
        return view('customer-measurements', [
            'title' => 'Customer measurements', 'customer' => $customer,
            'measurements' => $measurements, 'latest' => $measurements->first(),
        ]);
    }

    public function store(Request $request, Customer $customer)
    {
        $rules = [
            'garment' => 'sometimes|required|in:'.implode(',', array_keys(config('measurements.garments'))),
            'unit' => 'required|in:inches,cm', 'convention' => 'required|in:full,half',
            'measured_on' => 'required|date|before_or_equal:today',
            'taken_by' => ['required', 'string', 'max:100', new PersonName], 'fit' => 'required|in:regular,slim,loose',
            'notes' => 'nullable|string|max:2000', 'values' => 'required|array',
        ];
        $keys = [];
        foreach (config('measurements.sections') as $fields) {
            foreach ($fields as $key => $label) {
                $keys[] = $key;
                $rules['values.'.$key] = 'nullable|numeric|gt:0|max:300';
            }
        }
        $garment = $request->input('garment');
        if (is_string($garment) && array_key_exists($garment, config('measurements.garments'))) {
            $selectedKeys = [];
            foreach (config('measurements.order_sections')[$garment] as $section) $selectedKeys = array_merge($selectedKeys, array_keys(config('measurements.sections')[$section]));
            foreach (config('measurements.required_for_order')[$garment] as $key) $rules['values.'.$key] = 'required|numeric|gt:0|max:300';
            $keys = $selectedKeys;
        } else foreach (config('measurements.length_fields') as $length) {
            $otherLengths = array_filter(config('measurements.length_fields'), function ($field) use ($length) {
                return $field !== $length;
            });
            $rules['values.'.$length] .= '|required_without_all:'.implode(',', array_map(function ($field) {
                return 'values.'.$field;
            }, $otherLengths));
        }
        $attributes = ['taken_by' => "tailor's name", 'measured_on' => 'date measured'];
        foreach (config('measurements.sections') as $section => $fields) {
            foreach ($fields as $key => $label) {
                $attributes['values.'.$key] = $section.' '.strtolower($label);
            }
        }
        $data = $request->validate($rules, [], $attributes);
        $data['values'] = array_filter(array_intersect_key($data['values'], array_flip($keys)), function ($value) {
            return $value !== null && $value !== '';
        });
        unset($data['garment']);
        $saved = DB::transaction(function () use ($customer, $garment, $keys, $data) {
            // Serialize saves for one customer, including double-click submissions.
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $latest = $customer->measurements()->first();
            if ($garment && $latest && $latest->unit === $data['unit'] && $latest->convention === $data['convention']) {
                $data['values'] = array_diff_key($latest->values, array_flip($keys)) + $data['values'];
            }
            $unchanged = $latest && $latest->values == $data['values']
                && $latest->measured_on->toDateString() === $data['measured_on'];
            foreach (['unit', 'convention', 'fit', 'taken_by', 'notes'] as $field) {
                $unchanged = $unchanged && (string) $latest->$field === (string) ($data[$field] ?? '');
            }
            if ($unchanged) return false;
            $customer->measurements()->create($data);
            return true;
        });
        return ($request->boolean('order_flow') ? redirect()->route('orders.create', ['customer' => $customer, 'garment' => $garment, 'receipt_order' => $request->input('receipt_order')]) : redirect()->route('measurements.index', ['customer' => $customer, 'garment' => $garment]))
            ->with('message', $saved ? 'Measurements saved. Existing orders are unchanged.' : 'These measurements are already saved. No duplicate was added.')
            ->with('alert-type', 'success');
    }
}
