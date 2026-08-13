<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * admin/settings — a single edit form over Setting's key/value store,
 * Super Admin only. Currently just whatsapp_number.
 */
class SettingManagementTest extends TestCase
{
    use RefreshDatabase;

    // --- Access control -----------------------------------------------

    public function test_super_admin_can_view_the_settings_page(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk();
    }

    public function test_dispatcher_cannot_view_the_settings_page(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($dispatcher)->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_driver_cannot_view_the_settings_page(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.settings.edit'))->assertRedirect(route('login'));
    }

    public function test_dispatcher_cannot_update_settings(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $response = $this->actingAs($dispatcher)->put(route('admin.settings.update'), [
            'whatsapp_number' => '+96170000000',
        ]);

        $response->assertForbidden();
        $this->assertNull(Setting::get('whatsapp_number'));
    }

    // --- Updating the WhatsApp number ---------------------------------

    public function test_super_admin_can_set_the_whatsapp_number(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'whatsapp_number' => '+96170123456',
        ]);

        $response->assertRedirect(route('admin.settings.edit'));
        $this->assertSame('+96170123456', Setting::get('whatsapp_number'));
    }

    public function test_super_admin_can_change_an_existing_whatsapp_number(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Setting::set('whatsapp_number', '+96170111111');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'whatsapp_number' => '+96170222222',
        ]);

        $this->assertSame('+96170222222', Setting::get('whatsapp_number'));
        // Updated in place, not duplicated.
        $this->assertSame(1, Setting::where('key', 'whatsapp_number')->count());
    }

    public function test_whatsapp_number_can_be_cleared(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Setting::set('whatsapp_number', '+96170111111');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'whatsapp_number' => '',
        ]);

        $this->assertNull(Setting::get('whatsapp_number'));
    }

    public function test_settings_page_shows_the_currently_saved_whatsapp_number(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Setting::set('whatsapp_number', '+96170999999');

        $response = $this->actingAs($admin)->get(route('admin.settings.edit'));

        $response->assertOk();
        $response->assertSee('+96170999999');
    }

    // --- Setting model ---------------------------------------------------

    public function test_setting_get_returns_the_default_when_unset(): void
    {
        $this->assertNull(Setting::get('whatsapp_number'));
        $this->assertSame('fallback', Setting::get('whatsapp_number', 'fallback'));
    }
}
