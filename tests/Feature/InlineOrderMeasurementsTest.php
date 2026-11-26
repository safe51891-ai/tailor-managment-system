<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Orders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InlineOrderMeasurementsTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->actingAs(User::factory()->create()); }
    private function customer()
    {
        return Customer::create(['fullname' => 'Ali Khan', 'phone' => '03001234567', 'address' => '', 'city' => '', 'email' => '', 'gender' => '', 'comment' => '']);
    }
    private function booking(Customer $customer)
    {
        return ['customer' => $customer->id, 'garment' => 'set', 'quantity' => 1,
            'measurement_unit' => 'inches', 'measurement_convention' => 'full',
            'measurement_values' => ['kameez_length' => 40, 'shalwar_length' => 38],
            'status' => 'received', 'priority' => 'emergency', 'receiver' => 'Ali Khan',
            'recieved_on' => date('Y-m-d'), 'collecting_on' => date('Y-m-d', strtotime('+7 days')),
            'amount_charged' => 1000, 'amount_paid' => 100, 'styles' => ['daman' => 'straight']];
    }
    public function test_new_customer_returns_directly_to_order_and_has_editable_garment_fields()
    {
        $this->post('/customers', ['order_flow' => 1, 'fullname' => 'Ali Khan', 'phone' => '03001234567'])
            ->assertSessionHasNoErrors()->assertRedirect(route('orders.create', ['customer' => Customer::first()]));
        $this->get(route('orders.create', ['customer' => Customer::first()]))->assertOk()
            ->assertSee('name="measurement_values[kameez_length]"', false)
            ->assertSee('name="items[__INDEX__][measurement_values][waistcoat_length]"', false)
            ->assertSee('Completion / delivery date')->assertDontSee('Enter this customer&#039;s measurements before saving an order.', false);
    }
    public function test_one_save_records_inline_suit_and_shirt_sizes_and_combines_the_receipt()
    {
        $customer = $this->customer(); $data = $this->booking($customer);
        $data['items'] = [['garment' => 'shirt', 'quantity' => 2, 'amount_charged' => 800, 'amount_paid' => 0,
            'measurement_values' => ['shirt_length' => 30, 'shirt_sleeve' => 24]]];
        $saved = $this->post('/orders', $data)->assertSessionHasNoErrors();
        $orders = Orders::orderBy('id')->get(); $this->assertCount(2, $orders);
        $saved->assertRedirect(route('orders.print-options', $orders[0]));
        $options = $this->get(route('orders.print-options', $orders[0]))->assertOk()
            ->assertSee('Print for customer')->assertSee('Print for karigar')->assertSee('Suit (Shalwar Kameez)')->assertSee('Shirt');
        $options->assertSee(route('orders.receipt', ['order' => $orders[0], 'print_actions' => 1]), false)
            ->assertSee(e(route('orders.work-sheet', ['order' => $orders[0], 'all' => 1, 'print_actions' => 1])), false);
        $this->get(route('orders.receipt', ['order' => $orders[0], 'print_actions' => 1]))->assertOk()
            ->assertSee('Back to print copies')->assertSee(route('orders.print-options', $orders[0]), false);
        $this->get(route('orders.work-sheet', ['order' => $orders[0], 'all' => 1, 'print_actions' => 1]))->assertOk()
            ->assertSee('Back to print copies')->assertViewHas('sheetOrders', function ($rows) { return $rows->count() === 2; });
        $this->assertEquals(40, $orders[0]->measurement_snapshot['values']['kameez_length']);
        $this->assertEquals(30, $orders[1]->measurement_snapshot['values']['shirt_length']);
        $this->assertEquals($orders[0]->id, $orders[1]->receipt_group_id);
        foreach ($orders as $order) { $this->assertSame('emergency', $order->priority); $this->assertSame($data['collecting_on'], $order->collecting_on); }
        $this->assertEquals('straight', $orders[0]->stitching_styles['daman']);
        $latest = $customer->measurements()->first();
        $this->assertEquals(38, $latest->values['shalwar_length']); $this->assertEquals(24, $latest->values['shirt_sleeve']);
        $this->get(route('orders.receipt', $orders[0]))->assertOk()->assertSee('PKR 1,800.00');
    }
    public function test_changed_sizes_update_profile_without_changing_past_order_or_duplicating_unchanged_sizes()
    {
        $customer = $this->customer(); $data = $this->booking($customer);
        $this->post('/orders', $data)->assertSessionHasNoErrors(); $first = Orders::first();
        $this->post('/orders', $data)->assertSessionHasNoErrors(); $this->assertSame(1, $customer->measurements()->count());
        $data['measurement_values']['kameez_length'] = 42;
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $customer->measurements()->count());
        $this->assertEquals(40, $first->fresh()->measurement_snapshot['values']['kameez_length']);
        $this->assertEquals(42, $customer->measurements()->first()->values['kameez_length']);
    }
    public function test_invalid_additional_sizes_roll_back_every_order_and_profile_update()
    {
        $customer = $this->customer(); $data = $this->booking($customer);
        $data['items'] = [['garment' => 'shirt', 'quantity' => 1, 'amount_charged' => 800, 'amount_paid' => 0,
            'measurement_values' => ['shirt_length' => -1, 'shirt_sleeve' => 301]]];
        $this->post('/orders', $data)->assertSessionHasErrors(['items.0.measurement_values.shirt_length', 'items.0.measurement_values.shirt_sleeve']);
        $this->assertSame(0, Orders::count()); $this->assertSame(0, $customer->measurements()->count());
        unset($data['items']); unset($data['measurement_values']['shalwar_length']);
        $this->post('/orders', $data)->assertSessionHasErrors('measurement_values.shalwar_length');
        $this->assertSame(0, Orders::count());
    }
}
