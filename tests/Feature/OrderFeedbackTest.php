<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DriverFeedback;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * POST /track/{order:tracking_token}/feedback — the one write action a
 * customer can take from the public tracking page, and the one /track/*
 * route that isn't guest-accessible (see routes/web.php's own comment).
 */
class OrderFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsCustomer(Customer $customer): void
    {
        // Same established gotcha as OrderPlacementTest: not
        // actingAs($customer, 'customer'), whose shouldUse('customer')
        // side effect changes unrelated default-guard resolution.
        Auth::guard('customer')->login($customer);
    }

    public function test_the_orders_own_customer_can_leave_feedback_after_delivery(): void
    {
        $customer = Customer::factory()->withAccount()->create();
        $order = Order::factory()->delivered()->create(['customer_id' => $customer->id]);
        $this->loginAsCustomer($customer);

        $response = $this->post(route('track.feedback.store', $order->tracking_token), [
            'rating' => 5,
            'comment' => 'Great, fast delivery!',
        ]);

        $response->assertRedirect(route('track.show', $order->tracking_token));
        $this->assertDatabaseHas('driver_feedback', [
            'order_id' => $order->id,
            'driver_id' => $order->driver_id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'comment' => 'Great, fast delivery!',
        ]);
    }

    public function test_a_rating_without_a_comment_is_accepted(): void
    {
        $customer = Customer::factory()->withAccount()->create();
        $order = Order::factory()->delivered()->create(['customer_id' => $customer->id]);
        $this->loginAsCustomer($customer);

        $this->post(route('track.feedback.store', $order->tracking_token), ['rating' => 3])
            ->assertRedirect();

        $this->assertDatabaseHas('driver_feedback', ['order_id' => $order->id, 'rating' => 3, 'comment' => null]);
    }

    public function test_feedback_cannot_be_left_before_delivery(): void
    {
        $customer = Customer::factory()->withAccount()->create();
        $order = Order::factory()->onTheWay()->create(['customer_id' => $customer->id]);
        $this->loginAsCustomer($customer);

        $response = $this->post(route('track.feedback.store', $order->tracking_token), ['rating' => 5]);

        $response->assertSessionHasErrors('order');
        $this->assertDatabaseCount('driver_feedback', 0);
    }

    public function test_feedback_cannot_be_left_twice_for_the_same_order(): void
    {
        $customer = Customer::factory()->withAccount()->create();
        $order = Order::factory()->delivered()->create(['customer_id' => $customer->id]);
        $this->loginAsCustomer($customer);

        $this->post(route('track.feedback.store', $order->tracking_token), ['rating' => 4])->assertRedirect();
        $response = $this->post(route('track.feedback.store', $order->tracking_token), ['rating' => 1]);

        $response->assertSessionHasErrors('order');
        $this->assertDatabaseCount('driver_feedback', 1);
        $this->assertDatabaseHas('driver_feedback', ['order_id' => $order->id, 'rating' => 4]); // unchanged
    }

    public function test_a_different_customer_cannot_leave_feedback_on_someone_elses_order(): void
    {
        $owner = Customer::factory()->withAccount()->create();
        $stranger = Customer::factory()->withAccount()->create();
        $order = Order::factory()->delivered()->create(['customer_id' => $owner->id]);
        $this->loginAsCustomer($stranger);

        $response = $this->post(route('track.feedback.store', $order->tracking_token), ['rating' => 1]);

        $response->assertForbidden();
        $this->assertDatabaseCount('driver_feedback', 0);
    }

    public function test_a_dispatcher_created_orders_customer_can_still_leave_feedback(): void
    {
        // An order with no customer_id at all (a phone/WhatsApp order a
        // dispatcher entered with no matching Customer record) has no
        // "own customer" to authorize — authorize() must reject this,
        // not silently 500 on a null-vs-null comparison.
        $customer = Customer::factory()->withAccount()->create();
        $order = Order::factory()->delivered()->create(['customer_id' => null]);
        $this->loginAsCustomer($customer);

        $this->post(route('track.feedback.store', $order->tracking_token), ['rating' => 5])
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $order = Order::factory()->delivered()->create();

        $this->post(route('track.feedback.store', $order->tracking_token), ['rating' => 5])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('driver_feedback', 0);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $customer = Customer::factory()->withAccount()->create();
        $order = Order::factory()->delivered()->create(['customer_id' => $customer->id]);
        $this->loginAsCustomer($customer);

        $this->post(route('track.feedback.store', $order->tracking_token), ['rating' => 0])
            ->assertSessionHasErrors('rating');
        $this->post(route('track.feedback.store', $order->tracking_token), ['rating' => 6])
            ->assertSessionHasErrors('rating');
        $this->post(route('track.feedback.store', $order->tracking_token), [])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('driver_feedback', 0);
    }

    public function test_the_tracking_page_shows_the_submitted_rating_instead_of_the_form_afterward(): void
    {
        $customer = Customer::factory()->withAccount()->create();
        $order = Order::factory()->delivered()->create(['customer_id' => $customer->id]);
        DriverFeedback::factory()->create([
            'order_id' => $order->id,
            'driver_id' => $order->driver_id,
            'customer_id' => $customer->id,
            'rating' => 4,
            'comment' => 'Solid, would order again.',
        ]);
        $this->loginAsCustomer($customer);

        $this->get(route('track.show', $order->tracking_token))
            ->assertOk()
            ->assertSee(__('Thanks for rating your delivery!'))
            ->assertSee('Solid, would order again.')
            ->assertDontSee(route('track.feedback.store', $order->tracking_token), false);
    }

    public function test_a_visitor_who_isnt_the_orders_customer_sees_neither_the_form_nor_the_rating(): void
    {
        $owner = Customer::factory()->withAccount()->create();
        $order = Order::factory()->delivered()->create(['customer_id' => $owner->id]);
        DriverFeedback::factory()->create(['order_id' => $order->id, 'driver_id' => $order->driver_id, 'customer_id' => $owner->id]);

        // Not logged in at all — the public tracking page's default state.
        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertOk();
        $response->assertDontSee(route('track.feedback.store', $order->tracking_token), false);
        $response->assertDontSee(__('Thanks for rating your delivery!'));
    }

    public function test_the_form_is_double_submit_guarded(): void
    {
        $customer = Customer::factory()->withAccount()->create();
        $order = Order::factory()->delivered()->create(['customer_id' => $customer->id]);
        $this->loginAsCustomer($customer);

        $this->get(route('track.show', $order->tracking_token))
            ->assertOk()
            ->assertSee('x-data="preventDoubleSubmit"', false);
    }

    public function test_driver_feedback_is_write_once(): void
    {
        $feedback = DriverFeedback::factory()->create();

        $this->expectException(\LogicException::class);
        $feedback->update(['rating' => 1]);
    }
}
