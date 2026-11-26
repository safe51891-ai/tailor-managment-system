<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementSelectionTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->actingAs(User::factory()->create()); }
    private function customer($phone = '03001234567')
    {
        $this->post('/customers', ['fullname' => 'Ali Khan', 'phone' => $phone])->assertSessionHasNoErrors();
        return Customer::latest('id')->first();
    }
    private function data($garment, $values, $unit = 'inches', $convention = 'full')
    {
        return ['garment' => $garment, 'values' => $values, 'unit' => $unit, 'convention' => $convention, 'fit' => 'regular', 'taken_by' => 'Gul Khan', 'measured_on' => now()->toDateString()];
    }

    /** @dataProvider garmentTypes */
    public function test_each_dropdown_type_saves_its_fields($garment, $values)
    {
        $customer = $this->customer();
        $this->post(route('measurements.store', $customer), $this->data($garment, $values))->assertSessionHasNoErrors()->assertRedirect(route('measurements.index', ['customer' => $customer, 'garment' => $garment]));
        $this->assertSame($values, $customer->measurements()->first()->values);
        $this->get(route('measurements.index', ['customer' => $customer, 'garment' => $garment]))->assertOk()->assertSee('Measurement type / garment')->assertSee('id="measurement-garment"', false)->assertSee('showGarment');
    }
    public function garmentTypes()
    {
        return [['set', ['kameez_length' => 40, 'shalwar_length' => 38]], ['kameez', ['kameez_length' => 40]], ['shalwar', ['shalwar_length' => 38]], ['shirt', ['shirt_length' => 30, 'shirt_sleeve' => 24]], ['pant', ['pant_length' => 40]], ['waistcoat', ['waistcoat_length' => 25]], ['sleeved_waistcoat', ['sleeved_waistcoat_length' => 28, 'sleeved_waistcoat_sleeve' => 24]]];
    }

    public function test_saving_selected_type_preserves_other_sizes_and_ignores_unselected_input()
    {
        $customer = $this->customer();
        $this->post(route('measurements.store', $customer), $this->data('set', ['kameez_length' => 40, 'shalwar_length' => 38, 'chest' => 42]))->assertSessionHasNoErrors();
        $this->post(route('measurements.store', $customer), $this->data('shirt', ['shirt_length' => 30, 'shirt_sleeve' => 24, 'kameez_length' => 99]))->assertSessionHasNoErrors();
        $profile = $customer->measurements()->first();
        $this->assertSame(40, $profile->values['kameez_length']);
        $this->assertSame(30, $profile->values['shirt_length']);
        $this->post(route('measurements.store', $customer), $this->data('kameez', ['kameez_length' => 41]))->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('chest', $customer->measurements()->first()->values);
        $this->assertSame(30, $customer->measurements()->first()->values['shirt_length']);
        $this->assertSame(3, $customer->measurements()->count());
    }

    public function test_selected_type_requires_matching_sizes_and_different_units_do_not_merge()
    {
        $customer = $this->customer();
        $this->post(route('measurements.store', $customer), $this->data('shirt', ['kameez_length' => 40]))->assertSessionHasErrors(['values.shirt_length', 'values.shirt_sleeve']);
        $this->post(route('measurements.store', $customer), $this->data('set', ['kameez_length' => 40]))->assertSessionHasErrors('values.shalwar_length');
        $this->post(route('measurements.store', $customer), $this->data('invalid', ['kameez_length' => 40]))->assertSessionHasErrors('garment');
        $this->post(route('measurements.store', $customer), $this->data('set', ['kameez_length' => 40, 'shalwar_length' => 38]))->assertSessionHasNoErrors();
        $this->post(route('measurements.store', $customer), $this->data('shirt', ['shirt_length' => 76, 'shirt_sleeve' => 61], 'cm'))->assertSessionHasNoErrors();
        $this->assertSame(['shirt_length' => 76, 'shirt_sleeve' => 61], $customer->measurements()->first()->values);
        $this->post(route('measurements.store', $customer), $this->data('pant', ['pant_length' => 104], 'cm', 'half'))->assertSessionHasNoErrors();
        $this->assertSame(['pant_length' => 104], $customer->measurements()->first()->values);
        $this->assertSame(3, $customer->measurements()->count());
    }

    public function test_current_saved_measurements_show_kameez_and_shalwar_once_and_history_is_optional()
    {
        $customer = $this->customer();
        $this->post(route('measurements.store', $customer), $this->data('kameez', ['kameez_length' => 40, 'chest' => 42]))->assertSessionHasNoErrors();
        $original = $customer->measurements()->first();
        $this->post(route('measurements.store', $customer), $this->data('shalwar', ['shalwar_length' => 38]))->assertSessionHasNoErrors();
        $response = $this->get(route('measurements.index', $customer))->assertOk()->assertSee('Saved measurements')
            ->assertSee('View earlier measurement versions')->assertDontSee('id="measurement-history"', false);
        preg_match('/<section class="card" id="saved-measurements">(.*?)<\/section>/s', $response->getContent(), $match);
        $this->assertNotEmpty($match);
        $this->assertSame(1, substr_count($match[1], '>Kameez</h5>'));
        $this->assertSame(1, substr_count($match[1], '>Shalwar</h5>'));
        $this->assertSame(['kameez_length' => 40, 'chest' => 42], $original->fresh()->values);
        $this->get(route('measurements.index', ['customer' => $customer, 'history' => 1]))->assertOk()
            ->assertSee('Earlier measurement versions')->assertSee('Hide earlier versions');
    }

    public function test_identical_saves_do_not_create_duplicates_but_changes_keep_previous_sizes()
    {
        $customer = $this->customer();
        $data = $this->data('set', ['kameez_length' => 40, 'shalwar_length' => 38]);
        $this->post(route('measurements.store', $customer), $data)->assertSessionHasNoErrors();
        $original = $customer->measurements()->first();
        $data['values'] = ['shalwar_length' => '38.00', 'kameez_length' => '40.0'];
        $this->post(route('measurements.store', $customer), $data)->assertSessionHasNoErrors()
            ->assertSessionHas('message', 'These measurements are already saved. No duplicate was added.');
        $this->assertSame(1, $customer->measurements()->count());
        $data['values']['kameez_length'] = 41;
        $this->post(route('measurements.store', $customer), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $customer->measurements()->count());
        $this->assertSame(40, $original->fresh()->values['kameez_length']);
        $this->assertEquals(41, $customer->measurements()->first()->values['kameez_length']);
    }

    /** @dataProvider validPhones */
    public function test_local_and_international_numbers_save_and_search($input, $stored)
    {
        $customer = $this->customer($input);
        $this->assertSame($stored, $customer->phone);
        $this->get('/customers?'.http_build_query(['search' => $input]))->assertOk()->assertViewHas('customers', function ($rows) use ($customer) { return $rows->total() === 1 && $rows->first()->id === $customer->id; });
        $this->get('/customers?'.http_build_query(['search' => $stored]))->assertOk()->assertSee('Ali Khan');
        $this->get(route('measurements.index', $customer))->assertOk();
        $this->put(route('customers.update', $customer), ['fullname' => 'Updated Khan', 'phone' => $input])->assertSessionHasNoErrors();
    }
    public function validPhones()
    {
        return [['03001234567', '03001234567'], ['+923001234567', '03001234567'], ['+966512345678', '+966512345678'], ['+447700900123', '+447700900123']];
    }

    /** @dataProvider invalidPhones */
    public function test_invalid_local_or_international_formats_are_rejected($phone)
    {
        $this->post('/customers', ['fullname' => 'Ali Khan', 'phone' => $phone])->assertSessionHasErrors('phone');
        $this->assertSame(0, Customer::count());
    }
    public function invalidPhones()
    {
        return [['02001234567'], ['0300123456'], ['030012345678'], ['0551234567'], ['+9660512345678'], ['+96651234567'], ['+966412345678'], ['+9203001234567'], ['+0123456789'], ['+4412345678901234'], ['+44abc123456'], ['0300 1234567']];
    }

    public function test_search_normalizes_international_pakistan_and_formatted_query()
    {
        $customer = $this->customer('03001234567');
        foreach (['+92 300-1234567', '00923001234567', '0300 1234567'] as $phone) {
            $this->get('/customers?'.http_build_query(['search' => $phone]))->assertOk()->assertViewHas('customers', function ($rows) use ($customer) { return $rows->total() === 1 && $rows->first()->id === $customer->id; });
        }
    }
}
