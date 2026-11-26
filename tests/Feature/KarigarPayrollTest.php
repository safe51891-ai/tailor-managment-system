<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Orders;
use App\Models\Staff;
use App\Models\User;
use App\Services\EmployeePay;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KarigarPayrollTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00', 'Asia/Karachi'));
        $this->actingAs(User::factory()->create());
    }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function employee($method = 'per_suit', $rate = 500, $start = '2026-10-01')
    {
        $this->post('/staff', $this->employeeData($method, $rate, $start))->assertSessionHasNoErrors();
        return Staff::latest('id')->first();
    }
    private function employeeData($method, $rate, $start = '2026-10-01')
    {
        return ['fullname' => 'Gul Khan', 'phone' => '03001234567', 'is_karigar' => 1, 'employment_start' => $start, 'pay_method' => $method, 'pay_rate' => $rate];
    }
    private function orderData(Staff $employee)
    {
        $customer = Customer::create(['fullname' => 'Ali Khan', 'phone' => '03001234567', 'address' => '', 'city' => '', 'email' => '', 'comment' => '', 'gender' => '']);
        $measurement = $customer->measurements()->create(['measured_on' => '2026-10-09', 'taken_by' => 'Gul Khan', 'unit' => 'inches', 'convention' => 'full', 'fit' => 'regular', 'values' => ['kameez_length' => 40, 'shalwar_length' => 38, 'shirt_length' => 30, 'shirt_sleeve' => 24]]);
        return ['customer' => $customer->id, 'measurement_id' => $measurement->id, 'garment' => 'set', 'status' => 'stitching', 'recieved_on' => '2026-10-09', 'collecting_on' => '2026-10-15', 'receiver' => 'Ali Khan', 'amount_charged' => 6000, 'amount_paid' => 1000, 'quantity' => 3, 'karigar_id' => $employee->id, 'styles' => ['daman' => 'round', 'collar' => 'ban', 'shalwar' => 'traditional', 'instructions' => 'Two side pockets']];
    }

    public function test_assignment_styles_completion_and_pay_snapshot_are_preserved()
    {
        $employee = $this->employee();
        $data = $this->orderData($employee);
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $order = Orders::first();
        $this->assertSame('round', $order->stitching_styles['daman']);
        $this->get('/dashboard')->assertOk()->assertViewHas('period', 'weekly')->assertViewHas('employeeReport', function ($rows) { return $rows[0]['assigned'] === 1 && $rows[0]['assigned_units'] === 3 && $rows[0]['completed'] === 0; });
        $data['status'] = 'ready';
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->assertSame('1500.00', $order->fresh()->employee_earning);
        $this->assertSame('500.00', $order->fresh()->pay_snapshot['rate']);
        $completedAt = $order->fresh()->completed_at;
        $this->put('/staff', ['id' => $employee->id] + $this->employeeData('per_suit', 800))->assertSessionHasNoErrors();
        $data['status'] = 'delivered';
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->assertSame('1500.00', $order->fresh()->employee_earning);
        $this->assertTrue($completedAt->eq($order->fresh()->completed_at));
        $this->get('/dashboard?period=monthly')->assertOk()->assertViewHas('employeeTotal', 1500)->assertViewHas('employeeReport', function ($rows) { return $rows[0]['completed'] === 1 && $rows[0]['suits'] === 3 && $rows[0]['assigned'] === 0; });
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Round / Gol')->assertSee('Gul Khan')->assertSee('Two side pockets');
        $this->get('/staff?edit='.$employee->id)->assertOk();
        $this->delete('/orders', ['id' => $order->id])->assertSessionHas('alert-type', 'warning');
        $this->assertNotNull($order->fresh());
        foreach (['amount_charged' => 6500, 'karigar_id' => null, 'garment' => 'kameez'] as $field => $invalidValue) {
            $invalid = $data; $invalid[$field] = $invalidValue;
            $this->put(route('orders.update', $order), $invalid)->assertSessionHasErrors($field);
        }
        $this->get(route('orders.edit', $order))->assertOk();
        $data['quantity'] = 2;
        $this->put(route('orders.update', $order), $data)->assertSessionHasErrors('quantity');
        $data['quantity'] = 3; $data['status'] = 'stitching';
        $this->put(route('orders.update', $order), $data)->assertSessionHasErrors('status');
        $this->delete('/staff', ['id' => $employee->id])->assertSessionHas('alert-type', 'success');
        $data['status'] = 'delivered';
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertOk()->assertViewHas('employeeTotal', 1500);
    }

    /** @dataProvider payMethods */
    public function test_each_work_pay_method_uses_its_own_calculation($method, $rate, $garment, $expected)
    {
        $employee = $this->employee($method, $rate);
        $data = $this->orderData($employee); $data['status'] = 'ready'; $data['garment'] = $garment;
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $this->assertSame($expected, Orders::first()->employee_earning);
    }
    public function payMethods()
    {
        return [['percentage', 12.5, 'set', '750.00'], ['per_order', 400, 'set', '400.00'], ['per_suit', 500, 'set', '1500.00'], ['per_suit', 500, 'shirt', '0.00'], ['salary', 31000, 'set', '0.00']];
    }

    public function test_salary_proration_and_rate_history_use_pakistani_calendar_dates()
    {
        $employee = $this->employee('salary', 31000);
        $this->get('/dashboard')->assertViewHas('employeeTotal', 5000);
        $this->get('/dashboard?period=monthly')->assertViewHas('employeeTotal', 9000);
        $this->put('/staff', ['id' => $employee->id] + $this->employeeData('salary', 62000))->assertSessionHasNoErrors();
        $this->get('/dashboard')->assertViewHas('employeeTotal', 6000);
        $this->get('/dashboard?period=monthly')->assertViewHas('employeeTotal', 10000);
        $this->assertSame(2, $employee->payTerms()->count());
        $this->put('/staff', ['id' => $employee->id] + $this->employeeData('per_order', 500))->assertSessionHasNoErrors();
        $this->get('/dashboard?period=monthly')->assertViewHas('employeeTotal', 8000);
        $this->assertSame(2, $employee->payTerms()->count());
        $this->put('/staff', ['id' => $employee->id] + $this->employeeData('salary', 20000, '2026-09-01'))->assertSessionHasErrors('employment_start');
        $late = $this->employee('salary', 31000, '2026-10-08');
        $report = app(EmployeePay::class)->report(now()->startOfMonth(), now()->endOfDay());
        $this->assertEquals(2000, $report->firstWhere('employee.id', $late->id)['salary']);
    }

    public function test_week_crossing_two_months_prorates_each_month_separately()
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'Asia/Karachi'));
        $this->employee('salary', 31000);
        // Oct 26–31: 6 x 1000; Nov 1: 31000 / 30.
        $this->get('/dashboard')->assertViewHas('employeeTotal', 7033.33);
    }

    public function test_staff_and_style_validation_and_archive_protect_pending_work()
    {
        $bad = $this->employeeData('percentage', 101); $bad['fullname'] = 'Gul123'; $bad['phone'] = '123';
        $this->post('/staff', $bad)->assertSessionHasErrors(['fullname', 'phone']);
        $this->post('/staff', $this->employeeData('percentage', 101))->assertSessionHasErrors('pay_rate');
        $employee = $this->employee();
        $data = $this->orderData($employee); $data['quantity'] = 0; $data['styles']['daman'] = 'invalid';
        $this->post('/orders', $data)->assertSessionHasErrors(['quantity', 'styles.daman']);
        $data['quantity'] = 1; $data['styles'] = ['daman' => 'custom'];
        $this->post('/orders', $data)->assertSessionHasErrors('styles.instructions');
        $data['styles']['instructions'] = 'Custom daman instructions';
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $this->delete('/staff', ['id' => $employee->id])->assertSessionHas('alert-type', 'warning');
        $this->put('/staff', ['id' => $employee->id, 'is_karigar' => 0] + $this->employeeData('per_suit', 500))->assertSessionHasErrors('is_karigar');
        $other = $this->employee('per_order', 200); $other->update(['is_karigar' => false]);
        $data['karigar_id'] = $other->id;
        $this->post('/orders', $data)->assertSessionHasErrors('karigar_id');
        $other->delete();
        $this->post('/orders', $data)->assertSessionHasErrors('karigar_id');
    }

    public function test_expenses_use_purchase_date_and_weekly_default_with_monthly_option()
    {
        $category = ExpenseCategory::create(['name' => 'Shop supplies']);
        foreach ([['2026-10-02', 100], ['2026-10-08', 200], ['2026-09-30', 400]] as $row) Expense::create(['expense_category_id' => $category->id, 'purchase_date' => $row[0], 'price' => $row[1]]);
        $this->get('/dashboard')->assertViewHas('total_expense', 200);
        $this->get('/dashboard?period=monthly')->assertViewHas('total_expense', 300);
        $this->get('/expenses')->assertOk()->assertViewHas('period', 'weekly')->assertViewHas('total', 200);
        $this->get('/expenses?period=monthly')->assertOk()->assertViewHas('total', 300);
        $this->get('/expenses?edit='.Expense::first()->id)->assertOk();
        $this->post('/expenses', ['category' => $category->id, 'purchase_date' => '2026-10-10', 'price' => -1])->assertSessionHasErrors(['price', 'purchase_date']);
    }

    public function test_unassigned_orders_must_choose_a_karigar_before_completion()
    {
        $employee = $this->employee(); $data = $this->orderData($employee); $data['karigar_id'] = null;
        $this->post('/orders', $data)->assertSessionHasNoErrors(); $order = Orders::first();
        $data['status'] = 'ready';
        $this->put(route('orders.update', $order), $data)->assertSessionHasErrors('karigar_id');
        $this->assertNull($order->fresh()->completed_at);
        $data['karigar_id'] = $employee->id;
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->assertSame('1500.00', $order->fresh()->employee_earning);
    }

    public function test_old_completed_orders_do_not_gain_an_invented_completion_date()
    {
        $employee = $this->employee(); $data = $this->orderData($employee);
        $this->post('/orders', $data)->assertSessionHasNoErrors(); $order = Orders::first();
        $order->update(['status' => 'ready']); $data['status'] = 'delivered';
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->assertNull($order->fresh()->completed_at);
        $this->get('/dashboard')->assertViewHas('employeeTotal', 0);
    }
}
