<?php
namespace Tests\Feature;

use App\Models\Due;
use App\Models\DuePayment;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Orders;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DuesTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->actingAs(User::factory()->create()); }
    private function createDue($direction = 'receivable')
    {
        $this->post(route('dues.store'), ['direction' => $direction, 'person_name' => 'Ali Khan', 'phone' => '+923001234567',
            'category' => 'other', 'amount' => '1000.25', 'recorded_on' => today()->toDateString(), 'due_on' => today()->addDays(7)->toDateString()])->assertSessionHasNoErrors();
        return Due::latest('id')->first();
    }
    private function payment($amount)
    {
        return ['amount' => $amount, 'paid_on' => today()->toDateString(), 'submission_token' => (string)Str::uuid()];
    }

    public function test_receivable_partial_payments_post_income_once_and_exact_remaining_balance()
    {
        $due = $this->createDue();
        $this->assertSame('03001234567', $due->phone);
        $this->assertSame(0, Income::count());
        $data = $this->payment('300.10');
        $this->post(route('dues.payment', $due), $data)->assertSessionHasNoErrors();
        $this->post(route('dues.payment', $due), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, Income::count());
        $this->assertSame(1, DuePayment::count());
        $this->assertEquals(700.15, $due->fresh()->balance);
        $this->get(route('dues.show', $due))->assertOk()->assertSee('PKR 700.15')->assertSee('Income #')->assertSee('Payment history');
        $this->get(route('dues.receivable'))->assertOk()->assertSee('PKR 700.15');
        $this->post(route('dues.payment', $due), $this->payment('700.15'))->assertSessionHasNoErrors();
        $this->assertEquals(0, $due->fresh()->balance);
        $this->assertEquals(1000.25, Income::sum('amount'));
        $this->get(route('dues.show', $due))->assertOk()->assertSee('Fully settled');
        $this->get(route('dues.receivable'))->assertViewHas('dues', function ($rows) { return $rows->total() === 0; });
        $this->get(route('dues.receivable', ['status' => 'settled']))->assertViewHas('dues', function ($rows) { return $rows->total() === 1; });
    }

    public function test_payable_posts_expenses_and_payment_history_protects_linked_financial_records()
    {
        $due = $this->createDue('payable');
        $this->post(route('dues.payment', $due), $this->payment('100'))->assertSessionHasNoErrors();
        $expense = Expense::first();
        $this->assertNotNull($expense);
        $this->assertSame(0, Income::count());
        $this->assertEquals(900.25, $due->fresh()->balance);
        $this->get(route('dues.payable'))->assertOk()->assertSee('PKR 900.25');
        $this->delete(route('expense.destroy'), ['id' => $expense->id])->assertSessionHasErrors('id');
        $this->put(route('expenses'), ['id' => $expense->id])->assertSessionHasErrors('id');
        $this->delete(route('dues.destroy'), ['id' => $due->id])->assertSessionHasErrors('id');
        $this->assertNotNull($expense->fresh());
        $this->assertFalse($due->fresh()->trashed());
        $receive = $this->createDue();
        $this->post(route('dues.payment', $receive), $this->payment('50'))->assertSessionHasNoErrors();
        $income = Income::first();
        $this->delete(route('income.destroy'), ['id' => $income->id])->assertSessionHasErrors('id');
        $this->put(route('income'), ['id' => $income->id])->assertSessionHasErrors('id');
        $this->assertSame(1, Income::count());
    }

    public function test_overpayment_invalid_dates_precision_and_reused_payment_tokens_do_not_change_balances()
    {
        $due = $this->createDue();
        foreach (['1000.26', '-1', '0', '1.001'] as $amount) $this->post(route('dues.payment', $due), $this->payment($amount))->assertSessionHasErrors('amount');
        $data = $this->payment('10'); $data['paid_on'] = today()->subDay()->toDateString();
        $this->post(route('dues.payment', $due), $data)->assertSessionHasErrors('paid_on');
        $data['paid_on'] = today()->addDay()->toDateString();
        $this->post(route('dues.payment', $due), $data)->assertSessionHasErrors('paid_on');
        $this->assertSame(0, DuePayment::count());
        $data = $this->payment('10');
        $this->post(route('dues.payment', $due), $data)->assertSessionHasNoErrors();
        $other = $this->createDue('payable');
        $this->post(route('dues.payment', $other), $data)->assertSessionHasErrors('submission_token');
        $this->assertEquals(1000.25, $other->fresh()->balance);
        $this->assertSame(0, Expense::count());
        $this->assertSame(1, DuePayment::count());
    }

    public function test_dashboard_and_income_have_one_click_details_and_order_balances_are_separate()
    {
        $receive = $this->createDue(); $pay = $this->createDue('payable');
        $customer = Customer::create(['fullname' => 'Gul Khan', 'phone' => '03007654321', 'city' => '', 'email' => '', 'gender' => '', 'comment' => '', 'address' => '']);
        $order = Orders::create(['customer_id' => $customer->id, 'recieved_on' => today()->toDateString(), 'collecting_on' => today()->toDateString(), 'recieved_by' => 'Ali Khan', 'amount_charged' => '100.25', 'amount_paid' => '20.10', 'garment' => 'set', 'status' => 'received']);
        $this->get('/dashboard')->assertOk()->assertSee('Shop overview')->assertSee('Next deliveries')->assertSee('Money to pay')
            ->assertSee('View employee work and pay details')->assertSee(route('dues.receivable'))
            ->assertViewHas('orderBalance', function ($balance) { return (float)$balance === 80.15; })
            ->assertViewHas('receiveBalance', function ($balance) { return (float)$balance === 1000.25; });
        $this->get(route('income'))->assertOk()->assertSee('Money to receive')->assertSee('Money to pay');
        $this->get('/orders?balance=unpaid')->assertViewHas('orders', function ($rows) { return $rows->total() === 1; });
        $order->update(['amount_paid' => '100.25']);
        $this->get('/orders?balance=unpaid')->assertViewHas('orders', function ($rows) { return $rows->total() === 0; });
        $this->get(route('dues.receivable', ['search' => '+92 300 1234567']))->assertViewHas('dues', function ($rows) { return $rows->total() === 1; });
    }

    public function test_unpaid_due_deletion_confirmation_authentication_and_input_validation()
    {
        $due = $this->createDue();
        $this->get(route('dues.show', $due))->assertOk()->assertSee('Confirm delete')->assertSee('Yes, delete');
        $this->delete(route('dues.destroy'), ['id' => $due->id])->assertSessionHasNoErrors();
        $this->get(route('dues.show', $due))->assertNotFound();
        $this->post(route('dues.store'), ['direction' => 'wrong', 'person_name' => '123', 'amount' => 0])->assertSessionHasErrors(['direction', 'person_name', 'amount']);
        auth()->logout();
        foreach (['dues.receivable', 'dues.payable'] as $route) $this->get(route($route))->assertRedirect('/login');
        $this->post(route('dues.store'), [])->assertRedirect('/login');
    }
}
