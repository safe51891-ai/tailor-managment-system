<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Orders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TailoringWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function customer()
    {
        $this->post('/customers', ['fullname' => 'Test tailor customer', 'phone' => '03001234567'])->assertSessionHasNoErrors();
        return Customer::latest('id')->first();
    }

    private function sizes($customer, $length = 40)
    {
        $this->post(route('measurements.store', $customer), [
            'measured_on' => date('Y-m-d'), 'taken_by' => 'Test tailor', 'unit' => 'inches',
            'convention' => 'full', 'fit' => 'regular', 'notes' => 'Two pockets',
            'values' => ['kameez_length' => $length, 'shalwar_length' => 38, 'chest' => 42.5],
        ])->assertSessionHasNoErrors();
        return $customer->measurements()->orderByDesc('id')->first();
    }

    private function orderData($customer, $measurement)
    {
        return [
            'customer' => $customer->id, 'measurement_id' => $measurement->id,
            'garment' => 'set', 'status' => 'received', 'recieved_on' => date('Y-m-d'),
            'collecting_on' => date('Y-m-d', strtotime('+7 days')), 'receiver' => 'Test tailor',
            'amount_charged' => 2500, 'amount_paid' => 500,
        ];
    }

    /** @dataProvider invalidCustomerInputs */
    public function test_customer_validation_rejects_invalid_names_and_phone_numbers($name, $phone, $field)
    {
        $this->post('/customers', ['fullname' => $name, 'phone' => $phone])->assertSessionHasErrors($field);
        $this->assertSame(0, Customer::count());
        $customer = Customer::create(['fullname' => 'Existing customer', 'phone' => '03001234567', 'address' => '', 'city' => '', 'email' => '', 'comment' => '', 'gender' => '']);
        $this->put(route('customers.update', $customer), ['fullname' => $name, 'phone' => $phone])->assertSessionHasErrors($field);
        $this->assertSame('Existing customer', $customer->fresh()->fullname);
        $this->assertSame('03001234567', $customer->fresh()->phone);
    }

    public function invalidCustomerInputs()
    {
        return [
            ['Ali', '0300123456', 'phone'], ['Ali', '030012345678', 'phone'],
            ['Ali', '02001234567', 'phone'], ['Ali', '0300 1234567', 'phone'],
            ['Ali', '0300123456a', 'phone'], ['Ali', '۰۳۰۰۱۲۳۴۵۶۷', 'phone'],
            ['12345', '03001234567', 'fullname'], ['Ali123', '03001234567', 'fullname'],
            ['Ali@Khan', '03001234567', 'fullname'], ['---', '03001234567', 'fullname'],
            ['   ', '03001234567', 'fullname'], [[], '03001234567', 'fullname'],
        ];
    }

    public function test_names_support_urdu_accents_and_normal_name_punctuation()
    {
        foreach (["محمد علی", "José O'Connor", 'Anne-Marie Khan', 'M. Ali', 'D’Arcy'] as $name) {
            $this->post('/customers', ['fullname' => $name, 'phone' => '03001234567'])->assertSessionHasNoErrors();
        }
        $this->assertSame(5, Customer::count());
        $this->assertSame('03001234567', Customer::first()->phone);
    }

    public function test_tailor_and_receiver_names_reject_digits()
    {
        $customer = $this->customer();
        $measurement = $this->sizes($customer);
        $this->post(route('measurements.store', $customer), [
            'measured_on' => date('Y-m-d'), 'taken_by' => 'Tailor123', 'unit' => 'inches',
            'convention' => 'full', 'fit' => 'regular', 'values' => ['kameez_length' => 40],
        ])->assertSessionHasErrors('taken_by');
        $data = $this->orderData($customer, $measurement);
        $data['receiver'] = '123';
        $this->post('/orders', $data)->assertSessionHasErrors('receiver');
        $this->assertSame(0, Orders::count());
    }

    public function test_customer_can_be_created_and_edited_with_only_name_and_phone()
    {
        $customer = $this->customer();
        $this->assertSame('', $customer->email);
        $this->get(route('customers.edit', $customer))->assertOk();
        $this->put(route('customers.update', $customer), ['fullname' => 'Updated customer', 'phone' => '03001234567'])->assertRedirect(route('customers'));
        $this->assertSame('Updated customer', $customer->fresh()->fullname);
        $this->get('/customers?search=Updated')->assertOk()->assertSee('Updated customer');
    }

    public function test_order_measurements_survive_customer_updates_and_status_edits()
    {
        $customer = $this->customer();
        $measurement = $this->sizes($customer);
        $data = $this->orderData($customer, $measurement);
        $this->get(route('measurements.index', $customer))->assertOk()->assertSee('Kameez')->assertSee('Shalwar');
        $this->get(route('orders.create', ['customer' => $customer->id]))->assertOk();
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $order = Orders::first();
        $this->assertSame(40, $order->measurement_snapshot['values']['kameez_length']);
        $newMeasurement = $this->sizes($customer, 44);
        $this->assertSame(2, $customer->measurements()->count());
        $data['measurement_id'] = '';
        $employee = \App\Models\Staff::create(['fullname' => 'Test karigar', 'address' => '', 'gender' => '', 'phone' => '03001234567', 'salary' => 0, 'pay_method' => 'per_order', 'pay_rate' => 0, 'employment_start' => now()->toDateString(), 'is_karigar' => true]);
        $data['karigar_id'] = $employee->id;
        $data['status'] = 'ready';
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->assertSame(40, $order->fresh()->measurement_snapshot['values']['kameez_length']);
        $this->assertSame('ready', $order->fresh()->status);
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Two pockets');
        $this->get(route('orders.edit', $order))->assertOk();
        $this->get('/orders?status=ready')->assertOk()->assertSee($order->tracking_code);
        $data['measurement_id'] = $newMeasurement->id;
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->assertSame(44, $order->fresh()->measurement_snapshot['values']['kameez_length']);
    }

    public function test_measurements_cannot_be_reused_for_another_customer()
    {
        $first = $this->customer();
        $measurement = $this->sizes($first);
        $second = $this->customer();
        $data = $this->orderData($second, $measurement);
        $this->post('/orders', $data)->assertSessionHasErrors('measurement_id');
        $this->assertSame(0, Orders::count());
    }

    public function test_invalid_payments_dates_and_sizes_are_rejected()
    {
        $customer = $this->customer();
        $measurement = $this->sizes($customer);
        $data = $this->orderData($customer, $measurement);
        $data['amount_paid'] = 3000;
        $data['collecting_on'] = '2020-01-01';
        $this->post('/orders', $data)->assertSessionHasErrors(['amount_paid', 'collecting_on']);
        $this->post(route('measurements.store', $customer), [
            'measured_on' => date('Y-m-d'), 'taken_by' => 'Tailor', 'unit' => 'inches',
            'convention' => 'full', 'fit' => 'regular', 'values' => ['kameez_length' => -1],
        ])->assertSessionHasErrors('values.kameez_length');
        $this->assertSame(1, $customer->measurements()->count());
    }

    public function test_orders_require_measurements_for_each_selected_garment()
    {
        $customer = $this->customer();
        $measurement = $this->sizes($customer);
        $data = $this->orderData($customer, $measurement);
        $data['measurement_id'] = '';
        $this->post('/orders', $data)->assertSessionHasErrors('measurement_id');
        $measurement->update(['values' => ['kameez_length' => 40]]);
        $data['measurement_id'] = $measurement->id;
        $this->post('/orders', $data)->assertSessionHasErrors('measurement_id');
        $data['garment'] = 'kameez';
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $this->assertSame('kameez', Orders::first()->garment);
    }

    /** @dataProvider additionalGarments */
    public function test_new_garments_work_without_shalwar_kameez_measurements($garment, $values, $label)
    {
        $customer = $this->customer();
        $this->post(route('measurements.store', $customer), [
            'measured_on' => date('Y-m-d'), 'taken_by' => 'Tailor', 'unit' => 'cm',
            'convention' => 'full', 'fit' => 'regular', 'values' => $values,
        ])->assertSessionHasNoErrors();
        $measurement = $customer->measurements()->first();
        $this->assertSame($values, $measurement->values);
        $data = $this->orderData($customer, $measurement);
        $data['garment'] = $garment;
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $order = Orders::first();
        $this->assertSame($garment, $order->garment);
        $this->assertSame($values, $order->measurement_snapshot['values']);
        $this->get(route('orders.show', $order))->assertOk()->assertSee($label);
    }

    public function additionalGarments()
    {
        return [
            ['shirt', ['shirt_length' => 76, 'shirt_sleeve' => 61, 'shirt_chest' => 100], 'Shirt'],
            ['pant', ['pant_length' => 105, 'pant_inseam' => 78, 'pant_waist' => 85], 'Pant / Trouser'],
            ['waistcoat', ['waistcoat_length' => 65, 'waistcoat_chest' => 100], 'Sleeveless'],
            ['sleeved_waistcoat', ['sleeved_waistcoat_length' => 70, 'sleeved_waistcoat_sleeve' => 60], 'With Sleeves'],
        ];
    }

    public function test_sleeved_orders_require_sleeve_sizes_and_print_only_relevant_sections()
    {
        $customer = $this->customer();
        $measurement = $this->sizes($customer);
        $measurement->update(['values' => [
            'shirt_length' => 30, 'pant_length' => 40, 'waistcoat_length' => 25,
            'sleeved_waistcoat_length' => 27,
        ]]);
        $data = $this->orderData($customer, $measurement);
        $data['garment'] = 'shirt';
        $this->post('/orders', $data)->assertSessionHasErrors('measurement_id');
        $data['garment'] = 'sleeved_waistcoat';
        $this->post('/orders', $data)->assertSessionHasErrors('measurement_id');
        $data['garment'] = 'waistcoat';
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $order = Orders::first();
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Sleeveless')->assertDontSee('Outside leg length')->assertDontSee('Shirt length');
    }

    public function test_overview_and_daily_filters_exclude_delivered_orders()
    {
        $customer = $this->customer();
        $measurement = $this->sizes($customer);
        $data = $this->orderData($customer, $measurement);
        $data['collecting_on'] = date('Y-m-d');
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $due = Orders::first();
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $delivered = Orders::latest('id')->first();
        $delivered->update(['status' => 'delivered']);
        $this->get('/dashboard')->assertOk()->assertViewHas('dueToday', 1)->assertViewHas('activeOrders', 1)->assertViewHas('overdue', 0)->assertSee('Income &amp; expenses', false)->assertDontSee('Next on the cutting table');
        $this->get('/orders?due=today')->assertOk()->assertSee($due->tracking_code)->assertDontSee($delivered->tracking_code);
        $due->update(['collecting_on' => date('Y-m-d', strtotime('-1 day'))]);
        $this->get('/orders?due=overdue')->assertOk()->assertSee($due->tracking_code)->assertDontSee($delivered->tracking_code);
        $this->get('/dashboard')->assertViewHas('overdue', 1)->assertViewHas('dueToday', 0);
    }

    public function test_changing_customer_requires_their_own_measurements_and_protects_order_history()
    {
        $customer = $this->customer();
        $measurement = $this->sizes($customer);
        $data = $this->orderData($customer, $measurement);
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $order = Orders::first();
        $other = $this->customer();
        $data['customer'] = $other->id;
        $data['measurement_id'] = '';
        $this->put(route('orders.update', $order), $data)->assertSessionHasErrors('measurement_id');
        $this->assertSame($customer->id, $order->fresh()->customer_id);
        $this->delete('/customers', ['id' => $customer->id])->assertSessionHas('alert-type', 'warning');
        $this->assertNotNull($customer->fresh());
    }
}
