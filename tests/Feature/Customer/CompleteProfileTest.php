<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_profile_screen_can_be_rendered(): void
    {
        $customer = Customer::factory()->withAccount()->create(['phone' => null]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.complete-profile.edit'))
            ->assertOk();
    }

    public function test_a_customer_with_no_matching_phone_just_gets_their_own_row_updated(): void
    {
        $customer = Customer::factory()->withAccount()->create(['phone' => null]);

        $response = $this->actingAs($customer, 'customer')
            ->put(route('customer.complete-profile.update'), [
                'phone' => '+9613999999',
                'address' => '1 New St, Beirut',
            ]);

        $response->assertRedirect(route('restaurants.index'));
        $this->assertDatabaseCount('customers', 1);

        $customer->refresh();
        $this->assertSame('+9613999999', $customer->phone);
        $this->assertSame('1 New St, Beirut', $customer->address);
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_completing_profile_links_to_an_existing_staff_created_customer_by_phone(): void
    {
        // A dispatcher-entered walk-in: name + phone, no login.
        $legacy = Customer::factory()->create([
            'phone' => '+9613000000',
            'password' => null,
            'notes' => 'VIP — always tips well',
            'is_active' => false,
        ]);
        $order = Order::factory()->create(['customer_id' => $legacy->id]);

        // A brand new registration, unrelated row.
        $registrant = Customer::factory()->withAccount()->create([
            'phone' => null,
            'name' => 'New Online Name',
        ]);
        $registrantId = $registrant->id;

        $response = $this->actingAs($registrant, 'customer')
            ->put(route('customer.complete-profile.update'), [
                'phone' => '+9613000000',
                'address' => '2 Merge St, Beirut',
            ]);

        $response->assertRedirect(route('restaurants.index'));

        // Exactly one row survives at that phone, and it's the OLDER,
        // dispatcher-created one — not the freshly-registered duplicate.
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseMissing('customers', ['id' => $registrantId]);

        $legacy->refresh();
        $this->assertSame($registrant->email, $legacy->email);
        $this->assertSame($registrant->password, $legacy->password);
        $this->assertSame('2 Merge St, Beirut', $legacy->address);

        // The dispatcher's own data survives untouched.
        $this->assertSame('VIP — always tips well', $legacy->notes);
        $this->assertFalse($legacy->is_active);

        // The pre-existing order still resolves to the surviving row.
        $this->assertSame($legacy->id, $order->fresh()->customer_id);

        // The session followed the merge onto the surviving row, not the
        // deleted duplicate.
        $this->assertAuthenticatedAs($legacy, 'customer');
    }

    public function test_completing_profile_never_merges_into_a_row_that_already_has_a_password(): void
    {
        Customer::factory()->withAccount()->create(['phone' => '+9613111111']);

        $registrant = Customer::factory()->withAccount()->create(['phone' => null]);

        $this->actingAs($registrant, 'customer')
            ->put(route('customer.complete-profile.update'), ['phone' => '+9613111111']);

        // Two separate rows share that phone now — no account-takeover
        // via a guessed/known phone number.
        $this->assertDatabaseCount('customers', 2);
        $this->assertSame('+9613111111', $registrant->fresh()->phone);
    }

    public function test_an_already_complete_profile_redirects_away_instead_of_re_running(): void
    {
        $customer = Customer::factory()->withAccount()->create(['phone' => '+9613222222']);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.complete-profile.edit'))
            ->assertRedirect(route('restaurants.index'));

        $this->actingAs($customer, 'customer')
            ->put(route('customer.complete-profile.update'), ['phone' => '+9613222222'])
            ->assertRedirect(route('restaurants.index'));
    }
}
