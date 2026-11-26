<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Orders;
use App\Models\ShopProfile;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GroupedReceiptTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->actingAs(User::factory()->create()); }

    private function data(Customer $customer, $garment = 'set')
    {
        if (!$customer->measurements()->count()) $customer->measurements()->create([
            'unit' => 'inches', 'convention' => 'full', 'fit' => 'regular', 'measured_on' => date('Y-m-d'), 'taken_by' => 'Ali Khan',
            'values' => ['kameez_length' => 40, 'shalwar_length' => 38, 'waistcoat_length' => 26, 'shirt_length' => 30, 'shirt_sleeve' => 24, 'pant_length' => 39],
        ]);
        return ['customer' => $customer->id, 'measurement_id' => $customer->measurements()->first()->id,
            'garment' => $garment, 'quantity' => 1, 'status' => 'received', 'receiver' => 'Ali Khan',
            'recieved_on' => date('Y-m-d'), 'collecting_on' => date('Y-m-d', strtotime('+7 days')),
            'amount_charged' => 1000.25, 'amount_paid' => 100.10];
    }

    private function save(array $data)
    {
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        return Orders::latest('id')->first();
    }

    public function test_one_submit_saves_multiple_garments_with_one_customer_and_shared_dates()
    {
        $customer = Customer::create(['fullname' => 'Gul Khan', 'phone' => '03001234567', 'address' => '', 'city' => '', 'email' => '', 'gender' => '', 'comment' => '']);
        $data = $this->data($customer);
        $measurementId = $data['measurement_id'];
        $data['items'] = [
            ['garment' => 'waistcoat', 'quantity' => 2, 'amount_charged' => 700.50, 'amount_paid' => 200.20, 'measurement_id' => $measurementId, 'customer' => 99999, 'collecting_on' => '2000-01-01'],
            ['garment' => 'shirt', 'quantity' => 1, 'amount_charged' => 600.25, 'amount_paid' => 50.10, 'measurement_id' => $measurementId],
        ];
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        $orders = Orders::orderBy('id')->get();
        $this->assertCount(3, $orders);
        $this->assertSame(1, Customer::count());
        $this->assertSame(['set', 'waistcoat', 'shirt'], $orders->pluck('garment')->all());
        foreach ($orders as $order) {
            $this->assertEquals($customer->id, $order->customer_id);
            $this->assertSame($data['recieved_on'], $order->recieved_on);
            $this->assertSame($data['collecting_on'], $order->collecting_on);
            $this->assertNotEmpty($order->measurement_snapshot);
        }
        $this->assertEquals($orders->first()->id, $orders[1]->receipt_group_id);
        $this->get(route('orders.receipt', $orders->first()))->assertOk()->assertSee('PKR 2,301.00')->assertSee('PKR 350.40')->assertSee('PKR 1,950.60');
        $this->get(route('orders.create', ['customer' => $customer->id]))->assertOk()->assertSee('Add garment to this order')
            ->assertSee('Save complete customer order')->assertSee('id="extra-garment-template"', false);
    }

    public function test_invalid_additional_garment_rolls_back_the_entire_booking()
    {
        $customer = Customer::create(['fullname' => 'Gul Khan', 'phone' => '03001234567', 'address' => '', 'city' => '', 'email' => '', 'gender' => '', 'comment' => '']);
        $data = $this->data($customer);
        $data['items'] = [['garment' => 'waistcoat', 'quantity' => 1, 'amount_charged' => 100, 'amount_paid' => 101, 'measurement_id' => $data['measurement_id']]];
        $this->post('/orders', $data)->assertSessionHasErrors('items.0.amount_paid');
        $this->assertSame(0, Orders::count());
        $data['items'][0]['amount_paid'] = 0;
        $data['items'][0]['garment'] = 'sleeved_waistcoat';
        $this->post('/orders', $data)->assertSessionHasErrors('items.0.measurement_id');
        $this->assertSame(0, Orders::count());
        $data['items'][0]['garment'] = 'shirt';
        $other = Customer::create(['fullname' => 'Ali Khan', 'phone' => '03007654321', 'address' => '', 'city' => '', 'email' => '', 'gender' => '', 'comment' => '']);
        $data['items'][0]['measurement_id'] = $this->data($other)['measurement_id'];
        $this->post('/orders', $data)->assertSessionHasErrors('items.0.measurement_id');
        $this->assertSame(0, Orders::count());
        $this->get(route('orders.create', ['customer' => $customer->id]))->assertOk()->assertSee('name="items[0][garment]"', false);
    }

    public function test_grouped_customer_receipt_includes_only_explicitly_linked_garments_and_combined_totals()
    {
        $customer = Customer::create(['fullname' => 'Gul Khan', 'phone' => '03001234567', 'address' => '', 'city' => '', 'email' => '', 'gender' => '', 'comment' => '']);
        $suit = $this->save($this->data($customer));
        $waskat = $this->save(['receipt_order' => $suit->id] + $this->data($customer, 'waistcoat'));
        $shirt = $this->save(['receipt_order' => $waskat->id] + $this->data($customer, 'shirt'));
        $unrelated = $this->save($this->data($customer, 'pant'));
        $this->assertEquals($suit->id, $shirt->receipt_group_id);
        $this->get(route('orders.receipt', $waskat))->assertOk()->assertSee($suit->tracking_code)
            ->assertSee($waskat->tracking_code)->assertSee($shirt->tracking_code)->assertDontSee($unrelated->tracking_code)
            ->assertSee('Suit (Shalwar Kameez)')->assertSee('Waistcoat — Sleeveless')->assertSee('Shirt')
            ->assertSee('PKR 3,000.75')->assertSee('PKR 300.30')->assertSee('PKR 2,700.45')->assertDontSee('40 inches');
        $this->get('/orders?search='.$suit->tracking_code)->assertViewHas('orders', function ($orders) { return $orders->total() === 3; });
        $this->get(route('orders.show', $suit))->assertSee('Add another garment to this receipt')->assertSee('Print all karigar sheets');
        $this->get(route('orders.create', ['customer' => $customer->id, 'receipt_order' => $suit->id]))
            ->assertOk()->assertSee('name="receipt_order" value="'.$suit->id.'"', false)->assertSee('data-go-back', false);
    }

    public function test_karigar_sheets_use_separate_pages_assignments_and_only_relevant_measurements()
    {
        $customer = Customer::create(['fullname' => 'Gul Khan', 'phone' => '03001234567', 'address' => '', 'city' => '', 'email' => '', 'gender' => '', 'comment' => '']);
        $first = Staff::create(['fullname' => 'Suit Karigar', 'phone' => '03001111111', 'is_karigar' => 1, 'pay_method' => 'per_order', 'pay_rate' => 100, 'address' => '', 'gender' => '', 'salary' => 0]);
        $second = Staff::create(['fullname' => 'Waskat Karigar', 'phone' => '03002222222', 'is_karigar' => 1, 'pay_method' => 'per_order', 'pay_rate' => 100, 'address' => '', 'gender' => '', 'salary' => 0]);
        $suit = $this->save(['karigar_id' => $first->id] + $this->data($customer));
        $waskat = $this->save(['karigar_id' => $second->id, 'receipt_order' => $suit->id] + $this->data($customer, 'waistcoat'));
        $response = $this->get(route('orders.work-sheet', ['order' => $suit->id, 'all' => 1]))->assertOk()->assertSee('Suit Karigar')->assertSee('Waskat Karigar')
            ->assertSee('40 inches')->assertSee('38 inches')->assertSee('26 inches')->assertDontSee('30 inches')->assertDontSee('PKR');
        $this->assertSame(2, substr_count($response->getContent(), '<article class="karigar-page">'));
        $this->assertSame(1, substr_count($response->getContent(), '<h5 class="mt-2">Kameez</h5>'));
        $this->assertSame(1, substr_count($response->getContent(), '<h5 class="mt-2">Shalwar</h5>'));
        $this->get(route('orders.work-sheet', $waskat))->assertOk()->assertSee('Waskat Karigar')->assertDontSee('Suit Karigar')->assertDontSee('40 inches');
    }

    public function test_customer_mismatch_and_invalid_receipt_links_are_rejected_and_archiving_excludes_garment()
    {
        $customer = Customer::create(['fullname' => 'Gul Khan', 'phone' => '03001234567', 'address' => '', 'city' => '', 'email' => '', 'gender' => '', 'comment' => '']);
        $other = Customer::create(['fullname' => 'Ali Khan', 'phone' => '03007654321', 'address' => '', 'city' => '', 'email' => '', 'gender' => '', 'comment' => '']);
        $suit = $this->save($this->data($customer));
        $this->post('/orders', ['receipt_order' => $suit->id] + $this->data($other, 'shirt'))->assertSessionHasErrors('receipt_order');
        $this->post('/orders', ['receipt_order' => 99999] + $this->data($customer, 'shirt'))->assertSessionHasErrors('receipt_order');
        $waskat = $this->save(['receipt_order' => $suit->id] + $this->data($customer, 'waistcoat'));
        $this->put(route('orders.update', $waskat), $this->data($other, 'waistcoat'))->assertSessionHasErrors('customer');
        $waskat->delete();
        $this->get(route('orders.receipt', $suit))->assertOk()->assertDontSee($waskat->tracking_code)->assertSee('PKR 1,000.25');
        $this->assertSame(2, Orders::withTrashed()->count());
    }

    public function test_receipt_group_is_preserved_when_updating_customer_and_measurements_before_another_garment()
    {
        $customer = Customer::create(['fullname' => 'Gul Khan', 'phone' => '03001234567', 'address' => '', 'city' => '', 'email' => '', 'gender' => '', 'comment' => '']);
        $suit = $this->save($this->data($customer));
        $this->put(route('customers.update', $customer), ['fullname' => 'Gul Ahmed', 'phone' => '03001234567', 'order_flow' => 1, 'receipt_order' => $suit->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('orders.create', ['customer' => $customer, 'receipt_order' => $suit->id]));
        $this->post(route('measurements.store', $customer), ['garment' => 'waistcoat', 'values' => ['waistcoat_length' => 28],
            'unit' => 'inches', 'convention' => 'full', 'fit' => 'regular', 'taken_by' => 'Ali Khan', 'measured_on' => date('Y-m-d'),
            'order_flow' => 1, 'receipt_order' => $suit->id])->assertSessionHasNoErrors()
            ->assertRedirect(route('orders.create', ['customer' => $customer, 'garment' => 'waistcoat', 'receipt_order' => $suit->id]));
        $waskat = $this->save(['receipt_order' => $suit->id] + $this->data($customer, 'waistcoat'));
        $this->get(route('orders.work-sheet', $waskat))->assertOk()->assertSee('28 inches');
        $suit->delete();
        $this->get(route('orders.receipt', $waskat))->assertOk()->assertSee($suit->tracking_code)->assertSee('PKR 1,000.25');
        $this->get('/orders?search='.$suit->tracking_code)->assertViewHas('orders', function ($orders) { return $orders->total() === 1; });
    }

    public function test_shop_details_and_logo_are_saved_printed_and_protected_by_login()
    {
        Storage::fake('public');
        $this->put(route('shop-profile.update'), ['name' => 'Khyber Tailors', 'phone' => '091 1234567', 'address' => 'Main Bazaar, Peshawar',
            'logo' => UploadedFile::fake()->image('shop.png')])->assertSessionHasNoErrors();
        $shop = ShopProfile::current();
        Storage::disk('public')->assertExists($shop->logo_path);
        $this->get(route('shop-profile.logo'))->assertOk();
        $order = $this->save($this->data(Customer::create(['fullname' => 'Gul Khan', 'phone' => '03001234567', 'address' => '', 'city' => '', 'email' => '', 'gender' => '', 'comment' => ''])));
        foreach (['orders.receipt', 'orders.work-sheet'] as $route) $this->get(route($route, $order))->assertOk()
            ->assertSee('Khyber Tailors')->assertSee('091 1234567')->assertSee('Main Bazaar, Peshawar')->assertSee('class="receipt-logo"', false);
        $this->put(route('shop-profile.update'), ['name' => 'Khyber Tailors', 'remove_logo' => 1])->assertSessionHasNoErrors();
        $this->get(route('shop-profile.logo'))->assertNotFound();
        auth()->logout();
        $this->get(route('shop-profile.edit'))->assertRedirect('/login');
        $this->put(route('shop-profile.update'), ['name' => 'Changed'])->assertRedirect('/login');
    }
}
