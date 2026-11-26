<?php
namespace App\Services;

use App\Models\Orders;
use App\Models\Staff;
use Carbon\Carbon;

class EmployeePay
{
    public function completion(?Staff $employee, array $order): array
    {
        if (!$employee) return ['pay_snapshot' => null, 'employee_earning' => 0];
        $method = $employee->pay_method;
        $rate = (float) $employee->pay_rate;
        $suits = $order['garment'] === 'set' ? $order['quantity'] : 0;
        $amount = 0;
        if ($method === 'percentage') $amount = $order['amount_charged'] * $rate / 100;
        if ($method === 'per_order') $amount = $rate;
        if ($method === 'per_suit') $amount = $rate * $suits;
        return [
            'pay_snapshot' => ['method' => $method, 'rate' => $employee->pay_rate, 'price' => $order['amount_charged'], 'suits' => $suits, 'karigar_name' => $employee->fullname],
            'employee_earning' => number_format($amount, 2, '.', ''),
        ];
    }

    public function report(Carbon $start, Carbon $end)
    {
        $employees = Staff::withTrashed()->with('payTerms')->whereDate('employment_start', '<=', $end)->get();
        $completed = Orders::whereBetween('completed_at', [$start, $end])->get()->groupBy('karigar_id');
        $assigned = Orders::whereIn('status', ['received', 'cutting', 'stitching'])->whereNotNull('karigar_id')->get()->groupBy('karigar_id');
        return $employees->map(function ($employee) use ($start, $end, $completed, $assigned) {
            $salary = 0;
            // Calendar-day proration handles weeks crossing months and rate changes.
            for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
                if ($day->lt(Carbon::parse($employee->employment_start)->startOfDay())) continue;
                if ($employee->deleted_at && $day->gt($employee->deleted_at->copy()->startOfDay())) continue;
                $term = $employee->payTerms->first(function ($term) use ($day) { return $term->effective_from->lte($day); });
                if ($term && $term->method === 'salary') $salary += (float) $term->rate / $day->daysInMonth;
            }
            $jobs = $completed->get($employee->id, collect());
            $open = $assigned->get($employee->id, collect());
            $workPay = (float) $jobs->sum('employee_earning');
            return ['employee' => $employee, 'assigned' => $open->count(), 'assigned_units' => $open->sum('quantity'), 'completed' => $jobs->count(), 'suits' => $jobs->where('garment', 'set')->sum('quantity'), 'salary' => round($salary, 2), 'work_pay' => $workPay, 'total' => round($salary, 2) + $workPay];
        })->filter(function ($row) { return !$row['employee']->trashed() || $row['completed'] || $row['salary']; })->values();
    }
}
