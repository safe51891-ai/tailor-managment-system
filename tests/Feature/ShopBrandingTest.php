<?php
namespace Tests\Feature;

use App\Models\ShopProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_identity_and_theme_appear_in_workspace_and_sign_in_and_survive_partial_updates()
    {
        $this->actingAs(User::factory()->create());
        $this->put(route('shop-profile.update'), [
            'name' => 'Khyber Tailors', 'primary_color' => '#e6c150',
            'sidebar_color' => '#f0e6df', 'background_color' => '#edf3fc',
        ])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertOk()->assertSee('Khyber Tailors')->assertSee('Shop settings')
            ->assertSee('--shop-teal: #e6c150', false)->assertSee('--shop-sidebar: #f0e6df', false)
            ->assertSee('--shop-canvas: #edf3fc', false)->assertSee('--shop-action-text: #000000', false);
        $this->get('/settings')->assertRedirect(route('shop-profile.edit'));
        $this->get(route('shop-profile.edit'))->assertOk()->assertSee('Save shop settings')->assertSee('Choose your colors');
        $this->put(route('shop-profile.update'), ['name' => 'Khyber New Name'])->assertSessionHasNoErrors();
        $this->assertSame('#e6c150', ShopProfile::current()->primary_color);
        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('Khyber New Name')->assertSee('--shop-teal: #e6c150', false);
        $this->put(route('shop-profile.update'), ['name' => 'Attacker'])->assertRedirect('/login');
    }

    public function test_css_injection_invalid_images_and_invalid_colors_are_rejected()
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $this->put(route('shop-profile.update'), [
            'name' => 'Shop', 'primary_color' => '#123456;}</style><script>alert(1)</script>',
            'sidebar_color' => 'red', 'background_color' => '#fff',
            'logo' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'),
        ])->assertSessionHasErrors(['primary_color', 'sidebar_color', 'background_color', 'logo']);
        $this->assertSame(0, ShopProfile::count());
    }

    public function test_logo_is_visible_on_sign_in_without_exposing_settings_to_guests()
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $this->put(route('shop-profile.update'), ['name' => 'Shop', 'logo' => UploadedFile::fake()->image('shop.png')])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('class="shop-login-logo"', false);
        $this->get(route('shop-profile.logo'))->assertOk();
        $this->get(route('shop-profile.edit'))->assertRedirect('/login');
    }
}
