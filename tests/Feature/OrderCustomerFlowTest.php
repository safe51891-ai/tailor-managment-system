<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCustomerFlowTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->actingAs(User::factory()->create()); }
    private function customerData()
    {
        return ['fullname' => 'Ali Khan', 'phone' => '03001234567', 'address' => 'Main bazaar', 'city' => 'Peshawar',
            'email' => 'ali@example.com', 'gender' => 'Male', 'comment' => 'Two side pockets'];
    }

    public function test_new_customer_flow_saves_customer_and_measurements_then_returns_to_order()
    {
        $this->get('/orders/create')->assertOk()->assertSee('Add a new customer')->assertSee('Existing customer')->assertSee('Search &amp; load customer', false);
        $this->get('/customers/create?order_flow=1')->assertOk()->assertSee('name="order_flow"', false);
        $this->post('/customers', ['order_flow' => 1] + $this->customerData())->assertSessionHasNoErrors();
        $customer = Customer::first();
        $this->get(route('measurements.index', ['customer' => $customer, 'order_flow' => 1]))->assertOk()->assertSee('name="order_flow"', false);
        $this->post(route('measurements.store', $customer), [
            'order_flow' => 1, 'garment' => 'shirt', 'values' => ['shirt_length' => 30, 'shirt_sleeve' => 24],
            'unit' => 'inches', 'convention' => 'full', 'fit' => 'regular', 'taken_by' => 'Test Tailor', 'measured_on' => date('Y-m-d'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('orders.create', ['customer' => $customer, 'garment' => 'shirt']));
        $this->get(route('orders.create', ['customer' => $customer, 'garment' => 'shirt']))->assertOk()->assertSee('Edit customer details')->assertDontSee('Loaded customer record')->assertDontSee('Order for Ali Khan')
            ->assertSee('value="shirt"  selected', false)->assertSee('Garment measurements');
        $this->assertSame(1, $customer->measurements()->count());
    }

    public function test_phone_search_loads_all_customer_details_and_measurements_and_profile_update_returns_to_order()
    {
        $customer = Customer::create($this->customerData());
        $customer->measurements()->create(['measured_on' => date('Y-m-d'), 'taken_by' => 'Test Tailor', 'unit' => 'inches', 'convention' => 'full', 'fit' => 'regular', 'values' => ['kameez_length' => 40, 'shalwar_length' => 38]]);
        $response = $this->get('/orders/create?'.http_build_query(['phone' => '+92 300 1234567']))->assertOk()->assertViewHas('customer', function ($loaded) use ($customer) { return $loaded->id === $customer->id; });
        foreach (['Ali Khan', 'Edit customer details', 'Garment measurements'] as $text) $response->assertSee($text);
        $response->assertViewHas('measurements', function ($rows) { return $rows->count() === 1; });
        preg_match_all('/\bid="([^"]+)"/', $response->getContent(), $ids);
        $this->assertSame(count($ids[1]), count(array_unique($ids[1])), 'Every form field should have a unique ID.');
        $data = $this->customerData(); $data['fullname'] = 'Ali Ahmed'; $data['address'] = 'New address'; $data['phone'] = '+923001112222';
        $this->put(route('customers.update', $customer), ['order_flow' => 1] + $data)->assertSessionHasNoErrors()->assertRedirect(route('orders.create', ['customer' => $customer]));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'fullname' => 'Ali Ahmed', 'phone' => '03001112222', 'address' => 'New address']);
        $this->get('/orders/create?phone=03001112222')->assertOk()->assertSee('Ali Ahmed');
        $this->assertSame(1, Customer::count());
    }

    public function test_existing_measurements_can_be_updated_and_loaded_back_into_the_order()
    {
        $customer = Customer::create($this->customerData());
        $original = $customer->measurements()->create(['measured_on' => date('Y-m-d'), 'taken_by' => 'Test Tailor', 'unit' => 'inches', 'convention' => 'full', 'fit' => 'regular', 'values' => ['kameez_length' => 40, 'shalwar_length' => 38]]);
        $this->get(route('measurements.index', ['customer' => $customer, 'order_flow' => 1]))->assertOk()->assertSee('Back to order');
        $payload = ['order_flow' => 1, 'garment' => 'kameez', 'values' => ['kameez_length' => 42],
            'unit' => 'inches', 'convention' => 'full', 'fit' => 'regular', 'taken_by' => 'Test Tailor', 'measured_on' => date('Y-m-d')];
        $this->post(route('measurements.store', $customer), $payload)->assertSessionHasNoErrors()
            ->assertRedirect(route('orders.create', ['customer' => $customer, 'garment' => 'kameez']));
        $latest = $customer->measurements()->first();
        $this->assertEquals(42, $latest->values['kameez_length']);
        $this->assertEquals(38, $latest->values['shalwar_length']);
        $this->assertEquals(40, $original->fresh()->values['kameez_length']);
        $this->get(route('orders.create', ['customer' => $customer, 'garment' => 'kameez']))->assertOk()
            ->assertViewHas('measurements', function ($rows) use ($latest) { return $rows->first()->id === $latest->id; });
        $this->post(route('measurements.store', $customer), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, $customer->measurements()->count());
    }

    public function test_shared_number_requires_selection_and_missing_number_does_not_load_someone_else()
    {
        $first = Customer::create($this->customerData());
        $data = $this->customerData(); $data['fullname'] = 'Gul Khan'; $second = Customer::create($data);
        $this->get('/orders/create?phone=03001234567')->assertOk()->assertViewHas('customer', null)->assertSee('Choose the correct person');
        $this->get('/orders/create?phone=03001234567&customer='.$second->id)->assertOk()->assertViewHas('customer', function ($customer) use ($second) { return $customer->id === $second->id; });
        $this->get('/orders/create?phone=03009999999')->assertOk()->assertSee('No customer found')->assertViewHas('customer', null);
        $this->put(route('customers.update', $first), ['order_flow' => 1, 'fullname' => '123', 'phone' => '03'])->assertSessionHasErrors(['fullname', 'phone']);
        $this->assertSame('Ali Khan', $first->fresh()->fullname);
    }
}
