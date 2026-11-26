<?php
namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeModuleTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->actingAs(User::factory()->create()); }
    private function data()
    {
        return ['fullname' => 'Gul Khan', 'phone' => '03001234567', 'address' => 'Peshawar', 'is_karigar' => 1, 'employment_start' => now()->toDateString(), 'pay_method' => 'salary'];
    }
    public function test_role_is_created_and_assigned_inside_employee_form_without_separate_tabs()
    {
        $this->get('/staff?add=1')->assertOk()->assertDontSee('Employees &amp; pay', false)
            ->assertDontSee('Employee roles')->assertSee('New role name')->assertSee('Pay method');
        $this->post('/staff', ['new_role' => '  Master tailor  '] + $this->data())->assertSessionHasNoErrors();
        $employee = Staff::first();
        $this->assertSame('Master tailor', $employee->designation->title);
        $this->put('/staff', ['id' => $employee->id, 'new_role' => 'master tailor'] + $this->data())->assertSessionHasNoErrors();
        $this->assertSame(1, Designation::count());
        $invalid = $this->data(); $invalid['pay_method'] = 'percentage'; $invalid['pay_rate'] = 150;
        $this->post('/staff', ['new_role' => 'Never saved'] + $invalid)->assertSessionHasErrors('pay_rate');
        $this->assertSame(1, Designation::count());
    }
    public function test_employee_list_starts_with_add_and_search_and_opens_form_on_request()
    {
        $this->get('/staff')->assertOk()->assertSee('Add employee')->assertSee('Search employees')->assertViewHas('showForm', false)->assertDontSee('id="fullname"', false);
        $this->get('/staff?add=1')->assertOk()->assertViewHas('showForm', true)->assertSee('id="fullname"', false)->assertSee('Employee role')->assertSee('Monthly salary (PKR, optional)');
    }
    public function test_salary_is_optional_but_work_based_rates_are_required()
    {
        $role = Designation::create(['title' => 'Master tailor']);
        $data = ['designation_id' => $role->id] + $this->data();
        $this->post('/staff', $data)->assertSessionHasNoErrors();
        $employee = Staff::first();
        $this->assertSame('0.00', $employee->pay_rate);
        $this->assertSame('Peshawar', $employee->address);
        $this->assertEquals($role->id, $employee->designation_id);
        $this->assertSame('0.00', $employee->payTerms()->first()->rate);
        $data['pay_rate'] = 30000;
        $this->put('/staff', ['id' => $employee->id] + $data)->assertSessionHasNoErrors();
        $this->assertSame('30000.00', $employee->fresh()->pay_rate);
        foreach (['percentage', 'per_order', 'per_suit'] as $method) {
            $missingRate = $this->data(); $missingRate['pay_method'] = $method;
            $this->post('/staff', $missingRate)->assertSessionHasErrors('pay_rate');
        }
    }
    public function test_employee_search_supports_name_role_and_international_pakistan_number()
    {
        $role = Designation::create(['title' => 'Master tailor']);
        $this->post('/staff', ['designation_id' => $role->id] + $this->data())->assertSessionHasNoErrors();
        $employee = Staff::first();
        foreach (['Gul', 'Master tailor', '03001234567', '+923001234567'] as $search) {
            $this->get('/staff?'.http_build_query(['search' => $search]))->assertOk()->assertViewHas('staff', function ($rows) use ($employee) { return $rows->total() === 1 && $rows->first()->id === $employee->id; });
        }
        $this->get('/staff?search=Unknown')->assertOk()->assertSee('No employees match your search.');
    }
    public function test_employee_payment_accounts_are_optional_and_bank_numbers_keep_leading_zeros()
    {
        $this->post('/staff', $this->data())->assertSessionHasNoErrors();
        $employee = Staff::first();
        $this->assertNull($employee->account_number);
        $bank = ['payment_account_type' => 'bank', 'account_number' => '001234567890', 'bank_name' => 'Meezan Bank', 'account_title' => 'Gul Khan'];
        $this->put('/staff', ['id' => $employee->id] + $bank + $this->data())->assertSessionHasNoErrors();
        $this->assertSame('001234567890', $employee->fresh()->account_number);
        $this->assertSame('Meezan Bank', $employee->fresh()->bank_name);
        $this->assertSame(1, $employee->payTerms()->count());
        $this->get('/staff')->assertOk()->assertSee('Ending 7890')->assertDontSee('001234567890');
        $this->get('/staff?edit='.$employee->id)->assertOk()->assertSee('001234567890');
        $this->assertArrayNotHasKey('account_number', $employee->fresh()->toArray());
        $this->put('/staff', ['id' => $employee->id, 'payment_account_type' => 'cash'] + $this->data())->assertSessionHasNoErrors();
        $this->assertNull($employee->fresh()->account_number);
        $this->assertNull($employee->fresh()->bank_name);
    }

    public function test_wallet_numbers_save_and_invalid_account_details_are_rejected()
    {
        foreach (['easypaisa', 'jazzcash'] as $type) {
            $this->post('/staff', ['payment_account_type' => $type, 'account_number' => '03001234567'] + $this->data())->assertSessionHasNoErrors();
            $this->assertSame('03001234567', Staff::latest('id')->first()->account_number);
            $this->post('/staff', ['payment_account_type' => $type, 'account_number' => '12345'] + $this->data())->assertSessionHasErrors('account_number');
        }
        $this->post('/staff', ['account_number' => '001234'] + $this->data())->assertSessionHasErrors('payment_account_type');
        $this->post('/staff', ['payment_account_type' => 'unknown'] + $this->data())->assertSessionHasErrors('payment_account_type');
        $this->post('/staff', ['payment_account_type' => 'bank', 'account_number' => '<script>'] + $this->data())->assertSessionHasErrors('account_number');
        $this->post('/staff', ['payment_account_type' => 'cash', 'account_number' => '123456'] + $this->data())->assertSessionHasErrors('account_number');
        $this->get('/staff?add=1')->assertOk()->assertSee('Payment account (optional)')->assertSee('Easypaisa')->assertSee('JazzCash')->assertSee('Bank account');
    }

}
