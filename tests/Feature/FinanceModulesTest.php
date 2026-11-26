<?php
namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceModulesTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00', 'Asia/Karachi')); $this->actingAs(User::factory()->create()); }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }
    private function employee()
    {
        $this->post('/staff', ['fullname' => 'Gul Khan', 'phone' => '03001234567', 'is_karigar' => 1, 'pay_method' => 'salary', 'pay_rate' => 31000, 'employment_start' => '2026-10-01'])->assertSessionHasNoErrors();
        return Staff::first();
    }
    private function expenseData($name, $amount = 100)
    {
        return ['category' => ExpenseCategory::where('name', $name)->firstOrFail()->id, 'price' => $amount, 'purchase_date' => '2026-10-09', 'description' => 'Paid in cash'];
    }

    public function test_modules_have_separate_navigation_and_expense_categories()
    {
        foreach (['/staff', '/expenses', '/income'] as $url) $this->get($url)->assertOk()->assertSee('BUSINESS MODULES')->assertSee('Employees')->assertSee('Expenses')->assertSee('Income');
        foreach (['Shop rent', 'Electricity bills', 'Gas bills', 'Water bills', 'Internet / phone bills', 'Employee salaries', 'Tools', 'Sewing machines', 'Machine repairs / maintenance', 'Buttons', 'Buckram / Bukram', 'Thread', 'Other expenses'] as $name) {
            $this->assertNotNull(ExpenseCategory::where('name', $name)->first());
            $this->get('/expenses')->assertSee($name);
        }
    }

    public function test_expense_ledger_totals_filters_and_pagination_use_all_matching_records()
    {
        for ($i = 0; $i < 25; $i++) Expense::create([
            'expense_category_id' => ExpenseCategory::where('name', 'Thread')->first()->id,
            'price' => '10.10', 'purchase_date' => '2026-10-09', 'description' => 'Blue thread',
        ]);
        $employee = $this->employee();
        $this->post('/expenses', ['employee_id' => $employee->id] + $this->expenseData('Employee salaries', 45.67))->assertSessionHasNoErrors();
        Expense::create(['expense_category_id' => ExpenseCategory::where('name', 'Shop rent')->first()->id, 'price' => '9000.00', 'purchase_date' => '2026-10-02', 'description' => 'October rent']);
        $this->get('/expenses')->assertOk()->assertViewHas('showForm', false)->assertViewHas('recordCount', 26)
            ->assertViewHas('total', function ($value) { return abs($value - 298.17) < .001; })
            ->assertViewHas('employeeTotal', 45.67)->assertViewHas('expenses', function ($rows) { return $rows->total() === 26 && $rows->count() === 20; });
        $this->get('/expenses?page=2')->assertOk()->assertViewHas('recordCount', 26)->assertSee('298.17');
        $this->get('/expenses?search=Gul')->assertOk()->assertViewHas('recordCount', 1)->assertViewHas('total', 45.67);
        $category = ExpenseCategory::where('name', 'Thread')->first();
        $this->get('/expenses?filter_category='.$category->id)->assertOk()->assertViewHas('recordCount', 25)
            ->assertViewHas('total', function ($value) { return abs($value - 252.5) < .001; });
        $this->get('/expenses?period=custom&from=2026-10-01&to=2026-10-03')->assertOk()->assertViewHas('total', 9000)->assertViewHas('recordCount', 1);
        $this->get('/expenses?search=Missing')->assertOk()->assertSee('No expenses found')->assertViewHas('total', 0);
    }

    public function test_expense_form_validates_precision_and_preserves_custom_period_after_save()
    {
        $this->get('/expenses?add=1')->assertOk()->assertViewHas('showForm', true)->assertSee('Date paid')->assertSee('Save expense');
        foreach ([0, -1, '1.234', 'abc'] as $amount) $this->post('/expenses', $this->expenseData('Thread', $amount))->assertSessionHasErrors('price');
        $this->assertSame(0, Expense::count());
        $url = '/expenses?period=custom&from=2026-10-01&to=2026-10-09';
        $this->post($url, $this->expenseData('Thread', '12.34'))->assertSessionHasNoErrors()->assertRedirect($url);
        $this->get($url)->assertOk()->assertViewHas('total', 12.34);
        $this->get('/expenses?period=custom&from=2026-10-09&to=2026-10-01')->assertSessionHasErrors('to');
        $this->get('/expenses?period=custom')->assertSessionHasErrors(['from', 'to']);
    }

    public function test_salary_payment_links_to_employee_and_does_not_change_calculated_pay()
    {
        $employee = $this->employee();
        $data = $this->expenseData('Employee salaries', 2500);
        $this->post('/expenses', $data)->assertSessionHasErrors('employee_id');
        $data['employee_id'] = $employee->id;
        $this->post('/expenses', $data)->assertSessionHasNoErrors();
        $expense = Expense::first();
        $this->assertEquals($employee->id, $expense->employee_id);
        $this->get('/dashboard')->assertViewHas('employeeTotal', 5000)->assertViewHas('total_expense', 2500);
        $this->get('/expenses')->assertOk()->assertSee('Gul Khan')->assertSee('2,500.00');
        $this->get('/expenses?edit='.$expense->id)->assertOk();
        $employee->delete();
        $this->put('/expenses', ['id' => $expense->id] + $data)->assertSessionHasNoErrors();
        $this->get('/expenses?edit='.$expense->id)->assertOk()->assertSee('archived');
        $this->post('/expenses', $data)->assertSessionHasErrors('employee_id');
        $this->put('/expenses', ['id' => $expense->id] + $this->expenseData('Shop rent', 9000))->assertSessionHasNoErrors();
        $this->assertNull($expense->fresh()->employee_id);
    }

    public function test_supplies_and_bills_save_as_independent_expenses()
    {
        foreach (['Shop rent', 'Electricity bills', 'Tools', 'Sewing machines', 'Buttons', 'Buckram / Bukram', 'Thread'] as $category) $this->post('/expenses', $this->expenseData($category, 500))->assertSessionHasNoErrors();
        $this->assertSame(7, Expense::count());
        $this->get('/expenses')->assertViewHas('total', 3500);
        $this->assertSame(0, Income::count());
        $this->delete('/expense-categories', ['id' => ExpenseCategory::where('name', 'Thread')->first()->id])->assertSessionHas('alert-type', 'warning');
        $this->delete('/expense-categories', ['id' => ExpenseCategory::where('is_salary', true)->first()->id])->assertSessionHas('alert-type', 'warning');
    }

    public function test_income_crud_period_filters_and_dashboard_use_received_date()
    {
        $category = IncomeCategory::where('name', 'Stitching payments')->firstOrFail();
        $data = ['category' => $category->id, 'amount' => 2500, 'income_date' => '2026-10-02', 'description' => 'Order TS-10 deposit'];
        $this->post('/income?period=monthly', $data)->assertSessionHasNoErrors()->assertRedirect('/income?period=monthly');
        $old = Income::first();
        $data['income_date'] = '2026-10-09'; $data['amount'] = 1000;
        $this->post('/income', $data)->assertSessionHasNoErrors();
        $recent = Income::latest('id')->first();
        $this->get('/income')->assertOk()->assertViewHas('period', 'weekly')->assertViewHas('total', 1000);
        $this->get('/income?period=monthly')->assertOk()->assertViewHas('total', 3500);
        $this->get('/dashboard')->assertViewHas('total_income', 1000)
            ->assertViewHas('chartDays', ['05 Oct', '06 Oct', '07 Oct', '08 Oct', '09 Oct'])
            ->assertViewHas('chartIncome', [0.0, 0.0, 0.0, 0.0, 1000.0]);
        $this->get('/dashboard?period=monthly')->assertViewHas('total_income', 3500)
            ->assertViewHas('chartIncome', [0.0, 2500.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 1000.0]);
        $this->get('/income?edit='.$recent->id)->assertOk();
        $data['amount'] = 1200;
        $this->put('/income', ['id' => $recent->id] + $data)->assertSessionHasNoErrors();
        $this->get('/income')->assertViewHas('total', 1200);
        $this->delete('/income-categories', ['id' => $category->id])->assertSessionHas('alert-type', 'warning');
        $this->delete('/income', ['id' => $recent->id])->assertSessionHas('alert-type', 'success');
        $this->get('/income')->assertViewHas('total', 0);
        $this->get('/dashboard')->assertViewHas('chartIncome', [0.0, 0.0, 0.0, 0.0, 0.0]);
        $this->get('/dashboard?period=monthly')->assertViewHas('total_income', 2500);
        $this->assertNotNull($old->fresh());
        $this->assertSame(0, Expense::count());
    }

    public function test_finance_validation_rejects_invalid_amounts_dates_and_employee()
    {
        $data = ['category' => IncomeCategory::first()->id, 'amount' => -1, 'income_date' => '2026-10-10'];
        $this->post('/income', $data)->assertSessionHasErrors(['amount', 'income_date']);
        $data['amount'] = 'abc'; $data['category'] = 999999; $data['income_date'] = 'invalid';
        $this->post('/income', $data)->assertSessionHasErrors(['category', 'amount', 'income_date']);
        $this->post('/expenses', ['employee_id' => 999999] + $this->expenseData('Employee salaries'))->assertSessionHasErrors('employee_id');
        $this->assertSame(0, Income::count()); $this->assertSame(0, Expense::count());
    }
}
