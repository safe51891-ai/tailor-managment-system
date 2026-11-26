<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Orders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPriorityTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->actingAs(User::factory()->create()); }
    private function data()
    {
        $customer = Customer::create(['fullname' => 'Ali Khan', 'phone' => '03001234567', 'address' => '', 'city' => '', 'email' => '', 'comment' => '', 'gender' => '']);
        $measurement = $customer->measurements()->create(['unit' => 'inches', 'convention' => 'full', 'fit' => 'regular', 'measured_on' => now()->toDateString(), 'taken_by' => 'Gul Khan', 'values' => ['kameez_length' => 40, 'shalwar_length' => 38]]);
        return ['customer' => $customer->id, 'measurement_id' => $measurement->id, 'recieved_on' => now()->toDateString(), 'collecting_on' => now()->addDays(7)->toDateString(), 'receiver' => 'Gul Khan', 'garment' => 'set', 'status' => 'received', 'amount_charged' => 2500, 'amount_paid' => 0, 'priority' => 'emergency', 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(3)->toDateString()];
    }
    public function test_emergency_and_dates_save_and_display_without_duplicate_form_fields()
    {
        $data = $this->data();
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $order = Orders::first();
        $this->assertSame('emergency', $order->priority);
        $this->assertSame($data['start_date'], $order->start_date->toDateString());
        $this->assertSame($data['end_date'], $order->end_date->toDateString());
        $this->get(route('orders.show', $order))->assertOk()->assertSee('priority-emergency')->assertSee('Emergency')->assertDontSee('Work start:')->assertDontSee('Work end:');
        $this->get('/orders?priority=emergency')->assertOk()->assertSee($order->tracking_code);
        $this->get('/orders?priority=normal')->assertOk()->assertViewHas('orders', function ($orders) { return $orders->total() === 0; })->assertDontSee('<strong>'.$order->tracking_code.'</strong>', false);
        $this->get('/dashboard')->assertOk()->assertSee('finance-chart');
        $html = $this->get(route('orders.edit', $order))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'id="karigar_id"'));
        $this->assertSame(1, substr_count($html, 'id="quantity"'));
        foreach (['name="start_date"', 'name="end_date"', 'name="description"', 'name="styles[instructions]"', 'value="custom"'] as $removed) $this->assertStringNotContainsString($removed, $html);
        unset($data['priority'], $data['start_date'], $data['end_date']);
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->assertSame('emergency', $order->fresh()->priority);
        $this->assertSame(now()->addDays(3)->toDateString(), $order->fresh()->end_date->toDateString());
        $data['priority'] = 'normal';
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->assertSame('normal', $order->fresh()->priority);
        $data['collecting_on'] = now()->addDay()->toDateString();
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->assertSame($data['collecting_on'], $order->fresh()->end_date->toDateString());
    }
    public function test_invalid_priority_and_date_sequences_are_rejected()
    {
        $data = $this->data();
        foreach ([['priority', 'urgent'], ['start_date', now()->subDay()->toDateString()], ['end_date', now()->toDateString()], ['end_date', now()->addDays(8)->toDateString()], ['start_date', 'invalid'], ['end_date', '']] as $invalid) {
            $bad = $data; $bad[$invalid[0]] = $invalid[1];
            $this->post('/orders', $bad)->assertSessionHasErrors($invalid[0]);
        }
        $this->assertSame(0, Orders::count());
    }
    public function test_default_normal_and_date_defaults_and_same_day_emergency_are_supported()
    {
        $data = $this->data(); unset($data['priority'], $data['start_date'], $data['end_date']);
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $order = Orders::first();
        $this->assertSame('normal', $order->priority);
        $this->assertSame($data['recieved_on'], $order->start_date->toDateString());
        $this->assertSame($data['collecting_on'], $order->end_date->toDateString());
        $data['priority'] = 'emergency'; $data['start_date'] = $data['recieved_on']; $data['end_date'] = $data['recieved_on'];
        $this->post('/orders', $data)->assertSessionHasNoErrors();
    }
    public function test_sidebar_omits_unused_settings()
    {
        $html = $this->get('/dashboard')->assertOk()->getContent();
        preg_match('/<aside.*?<\/aside>/s', $html, $sidebar);
        $this->assertStringContainsString('Shop settings', $sidebar[0]);
        foreach (['Garment types', 'Measurement settings', 'Users', 'Preferences'] as $label) $this->assertStringNotContainsString($label, $sidebar[0]);
    }
}
