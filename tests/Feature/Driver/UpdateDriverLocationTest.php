<?php

namespace Tests\Feature\Driver;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PATCH /driver/location (Phase 5 §1/§7). Authorization goes through
 * DriverPolicy::update via UpdateDriverLocationRequest::authorize() —
 * there's no {driver} route parameter (same scoping as
 * AvailabilityController), so there's no "driver A updates driver B's
 * location" case to test: it's structurally always the authenticated
 * user's own Driver record, exactly like toggling availability.
 */
class UpdateDriverLocationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'latitude' => 33.8938,
            'longitude' => 35.5018,
            'accuracy' => 15.0,
        ], $overrides);
    }

    public function test_a_driver_can_submit_their_own_location(): void
    {
        $driver = Driver::factory()->create(['is_online' => true]);

        $response = $this->actingAs($driver->user)
            ->patchJson(route('driver.location.update'), $this->payload());

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $driver->refresh();
        $this->assertEqualsWithDelta(33.8938, (float) $driver->last_latitude, 0.0001);
        $this->assertEqualsWithDelta(35.5018, (float) $driver->last_longitude, 0.0001);
        $this->assertEqualsWithDelta(15.0, (float) $driver->last_accuracy, 0.01);
        $this->assertNotNull($driver->last_location_at);
        $this->assertTrue($driver->last_location_at->gt(now()->subMinute()));
    }

    public function test_a_fix_worse_than_the_configured_accuracy_ceiling_is_silently_dropped(): void
    {
        config(['runix.matching.max_location_accuracy_meters' => 100]);
        $driver = Driver::factory()->create(['is_online' => true]);

        $response = $this->actingAs($driver->user)
            ->patchJson(route('driver.location.update'), $this->payload(['accuracy' => 150]));

        // The request still succeeds — a bad GPS fix is never an error to
        // the driver, it just doesn't move the needle.
        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $driver->refresh();
        $this->assertNull($driver->last_latitude);
        $this->assertNull($driver->last_longitude);
        $this->assertNull($driver->last_location_at);
    }

    public function test_a_fix_exactly_at_the_accuracy_ceiling_is_accepted(): void
    {
        config(['runix.matching.max_location_accuracy_meters' => 100]);
        $driver = Driver::factory()->create(['is_online' => true]);

        $response = $this->actingAs($driver->user)
            ->patchJson(route('driver.location.update'), $this->payload(['accuracy' => 100]));

        $response->assertOk();

        $driver->refresh();
        $this->assertNotNull($driver->last_location_at);
    }

    /**
     * Validation/auth failures on this route render the same way every
     * other non-api/* route in the app does (bootstrap/app.php scopes
     * shouldRenderJsonWhen to api/* only) — a redirect with flashed
     * session errors, not a JSON error body. Only the success path is
     * JSON (per the approved spec: "a simple JSON success response"),
     * matched by ->patchJson() further down instead of ->patch() only so
     * these requests still exercise the endpoint the same way the real
     * client does; the response format itself is governed by the app,
     * not by the request's Accept header.
     */
    public function test_latitude_out_of_range_is_rejected(): void
    {
        $driver = Driver::factory()->create(['is_online' => true]);

        $response = $this->actingAs($driver->user)
            ->patch(route('driver.location.update'), $this->payload(['latitude' => 95]));

        $response->assertSessionHasErrors('latitude');
        $driver->refresh();
        $this->assertNull($driver->last_location_at);
    }

    public function test_longitude_out_of_range_is_rejected(): void
    {
        $driver = Driver::factory()->create(['is_online' => true]);

        $response = $this->actingAs($driver->user)
            ->patch(route('driver.location.update'), $this->payload(['longitude' => -200]));

        $response->assertSessionHasErrors('longitude');
        $driver->refresh();
        $this->assertNull($driver->last_location_at);
    }

    public function test_missing_fields_are_rejected(): void
    {
        $driver = Driver::factory()->create(['is_online' => true]);

        $response = $this->actingAs($driver->user)
            ->patch(route('driver.location.update'), []);

        $response->assertSessionHasErrors(['latitude', 'longitude', 'accuracy']);
    }

    public function test_a_user_with_no_driver_record_gets_a_404(): void
    {
        // A driver-role User without a Driver row — same edge case
        // AvailabilityController already guards against.
        $user = User::factory()->create(['role' => UserRole::DRIVER]);

        $response = $this->actingAs($user)
            ->patchJson(route('driver.location.update'), $this->payload());

        $response->assertNotFound();
    }

    public function test_a_dispatcher_cannot_access_the_driver_only_route(): void
    {
        $dispatcher = User::factory()->create(['role' => UserRole::DISPATCHER]);

        $response = $this->actingAs($dispatcher)
            ->patchJson(route('driver.location.update'), $this->payload());

        $response->assertForbidden();
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        // Same as every other route in the app (no api/* prefix here) —
        // the auth middleware redirects toward login rather than a bare
        // 401, per bootstrap/app.php's shouldRenderJsonWhen scoping.
        $response = $this->patch(route('driver.location.update'), $this->payload());

        $response->assertRedirect(route('login'));
    }

    public function test_submitting_while_offline_still_persists_the_location(): void
    {
        // The client-side JS never calls this endpoint while offline, but
        // the server doesn't need to trust that — nothing here depends on
        // is_online, so a stray request while offline still just records
        // the location rather than erroring. is_online alone still governs
        // offer eligibility, unchanged.
        $driver = Driver::factory()->create(['is_online' => false]);

        $response = $this->actingAs($driver->user)
            ->patchJson(route('driver.location.update'), $this->payload());

        $response->assertOk();
        $driver->refresh();
        $this->assertNotNull($driver->last_location_at);
    }
}
