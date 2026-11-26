<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Orders;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function booking()
    {
        $this->post('/customers', ['fullname' => 'Gul Khan', 'phone' => '03001234567'])->assertSessionHasNoErrors();
        $customer = Customer::latest('id')->first();
        $this->post(route('measurements.store', $customer), [
            'measured_on' => date('Y-m-d'), 'taken_by' => 'Test Tailor', 'unit' => 'inches', 'convention' => 'full',
            'fit' => 'regular', 'notes' => 'PRIVATE MEASUREMENT NOTE',
            'values' => ['kameez_length' => 40, 'shalwar_length' => 38],
        ])->assertSessionHasNoErrors();
        $data = ['customer' => $customer->id, 'measurement_id' => $customer->measurements()->first()->id,
            'garment' => 'set', 'quantity' => 2, 'status' => 'received', 'priority' => 'emergency',
            'recieved_on' => date('Y-m-d'), 'collecting_on' => date('Y-m-d', strtotime('+7 days')), 'receiver' => 'Test Tailor',
            'amount_charged' => 2500.50, 'amount_paid' => 500.25,
            'styles' => ['instructions' => 'PRIVATE STITCHING NOTE'],
            'tracking_code' => 'USER-SUPPLIED-CODE',
        ];
        $this->post('/orders', $data)->assertSessionHasNoErrors();
        return [Orders::latest('id')->first(), $data];
    }

    public function test_tracking_codes_are_stored_unique_and_stable_after_edits_and_archive()
    {
        [$first, $data] = $this->booking();
        $code = Orders::trackingCodeFor($first->id);
        $this->assertSame($code, $first->tracking_code);
        $this->assertDatabaseHas('orders', ['id' => $first->id, 'tracking_code' => $code]);
        $data['amount_paid'] = 750.25;
        $this->put(route('orders.update', $first), $data)->assertSessionHasNoErrors();
        $this->assertSame($code, $first->fresh()->tracking_code);
        $first->delete();
        [$second] = $this->booking();
        $this->assertNotSame($code, $second->tracking_code);
        $this->assertSame($code, Orders::withTrashed()->find($first->id)->tracking_code);
    }

    public function test_receipt_shows_customer_totals_and_matching_tag_without_internal_notes_or_payroll()
    {
        [$order] = $this->booking();
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Print customer receipt')->assertSee('Print clothes tag')->assertSee($order->tracking_code);
        $this->get(route('orders.receipt', $order))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee($order->tracking_code)->assertSee('Gul Khan')->assertSee('03001234567')
            ->assertSee('PKR 2,500.50')->assertSee('PKR 500.25')->assertSee('PKR 2,000.25')
            ->assertSee('2 suit(s)')->assertSee('Collection date')->assertSee('window.print()', false)
            ->assertDontSee('PRIVATE MEASUREMENT NOTE')->assertDontSee('PRIVATE STITCHING NOTE')->assertDontSee('Saved work earnings')->assertDontSee('Measurements saved');
        $this->get(route('orders.tag', $order))->assertOk()->assertSee($order->tracking_code)->assertSee('Gul Khan')->assertSee('2 suit(s)')->assertSee('EMERGENCY')
            ->assertDontSee('PKR')->assertDontSee('03001234567')->assertDontSee('PRIVATE MEASUREMENT NOTE')->assertDontSee('PRIVATE STITCHING NOTE');
        $order->update(['amount_paid' => 2500.50]);
        $this->get(route('orders.receipt', $order))->assertOk()->assertSee('Paid in full.')->assertSee('PKR 0.00');
    }

    public function test_search_finds_full_tracking_code_old_reference_numeric_id_and_mobile()
    {
        [$order] = $this->booking();
        foreach ([$order->tracking_code, strtolower($order->tracking_code), 'TS-'.$order->id, (string) $order->id, '03001234567'] as $search) {
            $this->get('/orders?'.http_build_query(['search' => $search]))->assertOk()
                ->assertViewHas('orders', function ($orders) use ($order) { return $orders->total() === 1 && $orders->first()->id === $order->id; });
        }
        $this->get('/orders?search=TS-999999')->assertViewHas('orders', function ($orders) { return $orders->total() === 0; });
    }

    public function test_receipt_and_tag_require_login_and_deleted_orders_are_not_printable()
    {
        [$order] = $this->booking();
        $receipt = route('orders.receipt', $order); $tag = route('orders.tag', $order); $sheet = route('orders.work-sheet', $order);
        auth()->logout();
        foreach ([$receipt, $tag, $sheet] as $url) $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $order->delete();
        foreach ([$receipt, $tag, $sheet] as $url) $this->get($url)->assertNotFound();
    }

    public function test_database_rejects_a_duplicate_tracking_code()
    {
        [$first] = $this->booking(); [$second] = $this->booking();
        $this->expectException(QueryException::class);
        DB::table('orders')->where('id', $second->id)->update(['tracking_code' => $first->tracking_code]);
    }

    public function test_karigar_sheet_has_assignment_dates_saved_sizes_and_instructions_without_financials()
    {
        [$order, $data] = $this->booking();
        $this->post('/staff', ['fullname' => 'Salim Khan', 'phone' => '03007654321', 'is_karigar' => 1,
            'pay_method' => 'per_order', 'pay_rate' => 300, 'employment_start' => date('Y-m-d')])->assertSessionHasNoErrors();
        $data['karigar_id'] = Staff::latest('id')->first()->id;
        $data['styles']['daman'] = 'round';
        $this->put(route('orders.update', $order), $data)->assertSessionHasNoErrors();
        $this->post(route('measurements.store', $order->customer_id), [
            'garment' => 'set', 'measured_on' => date('Y-m-d'), 'taken_by' => 'Test Tailor', 'unit' => 'inches',
            'convention' => 'full', 'fit' => 'regular', 'values' => ['kameez_length' => 99, 'shalwar_length' => 98],
        ])->assertSessionHasNoErrors();
        $this->get(route('orders.work-sheet', $order))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee($order->tracking_code)->assertSee('Gul Khan')->assertSee('Salim Khan')->assertSee('2 suit(s)')
            ->assertSee(\Carbon\Carbon::parse($data['recieved_on'])->format('d M Y'))
            ->assertSee(\Carbon\Carbon::parse($data['collecting_on'])->format('d M Y'))
            ->assertSee('Kameez')->assertSee('Shalwar')->assertSee('40 inches')->assertSee('38 inches')->assertDontSee('99 inches')
            ->assertSee('Round / Gol')->assertSee('PRIVATE STITCHING NOTE')->assertSee('PRIVATE MEASUREMENT NOTE')
            ->assertSee('EMERGENCY')->assertSee('window.print()', false)->assertDontSee('PKR')->assertDontSee('Remaining balance');
        $this->get(route('orders.show', $order))->assertSee('Print karigar measurement sheet');
        $this->get(route('orders.receipt', $order))->assertDontSee('Salim Khan')->assertDontSee('PRIVATE STITCHING NOTE');
    }

    public function test_karigar_sheet_only_prints_the_order_garment_and_handles_missing_sizes()
    {
        [$order] = $this->booking();
        $snapshot = $order->measurement_snapshot;
        foreach (config('measurements.sections') as $fields) {
            foreach ($fields as $key => $label) $snapshot['values'][$key] = 25;
        }
        foreach (config('measurements.order_sections') as $garment => $sections) {
            $order->update(['garment' => $garment, 'measurement_snapshot' => $snapshot, 'stitching_styles' => []]);
            $response = $this->get(route('orders.work-sheet', $order))->assertOk();
            foreach (array_keys(config('measurements.sections')) as $section) {
                $heading = '<h5 class="mt-2">'.$section.'</h5>';
                if (in_array($section, $sections)) $response->assertSee($heading, false);
                else $response->assertDontSee($heading, false);
            }
        }
        $order->update(['measurement_snapshot' => null]);
        $this->get(route('orders.work-sheet', $order))->assertOk()->assertSee('No measurements recorded for this order.')
            ->assertSee('Unassigned')->assertDontSee('25 inches');
    }

    public function test_existing_orders_receive_codes_including_archived_orders()
    {
        // Re-create the legacy shape inside this test's isolated transaction.
        Schema::drop('orders');
        Schema::create('orders', function (Blueprint $table) { $table->id(); $table->softDeletes(); });
        DB::table('orders')->insert([['id' => 7, 'deleted_at' => null], ['id' => 25, 'deleted_at' => now()]]);
        (new \AddOrderTrackingCode)->up();
        $this->assertDatabaseHas('orders', ['id' => 7, 'tracking_code' => 'TS-000007']);
        $this->assertDatabaseHas('orders', ['id' => 25, 'tracking_code' => 'TS-000025']);
    }
}
