<?php

namespace Tests\Feature;

use App\Enums\FeePayer;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9 — Arabic language & RTL localization. Covers the locale
 * middleware/switch route, layout dir/lang attributes, enum label
 * translation, and end-to-end Arabic rendering on a representative page
 * per audience (guest, admin, public tracking). Deliberately does not
 * duplicate RoleAuthorizationTest's own admin/dispatch/driver access
 * checks — those are unaffected by this phase and stay untouched.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    // --- Default / fallback behavior --------------------------------------

    public function test_default_locale_remains_english(): void
    {
        $this->get('/login');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_english_behavior_remains_unchanged_with_no_locale_switch(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Sign in');
        $response->assertSee('lang="en"', false);
        $response->assertSee('dir="ltr"', false);
    }

    // --- Switch route -----------------------------------------------------

    public function test_locale_switch_to_english_works(): void
    {
        $response = $this->withSession(['locale' => 'ar'])->get('/locale/en');

        $response->assertRedirect();
        $this->assertSame('en', session('locale'));
    }

    public function test_locale_switch_to_arabic_works(): void
    {
        $response = $this->get('/locale/ar');

        $response->assertRedirect();
        $this->assertSame('ar', session('locale'));
    }

    public function test_invalid_locale_returns_404(): void
    {
        $response = $this->get('/locale/fr');

        $response->assertNotFound();
        $this->assertNull(session('locale'));
    }

    /**
     * Regression for a real finding from a backend security review:
     * redirect()->back() trusts the client-supplied Referer header first
     * (Illuminate\Routing\UrlGenerator::previous()), which would make this
     * public, unauthenticated endpoint an open redirect if a spoofed
     * Referer were ever honored. The fix reads session()->previousUrl()
     * directly instead — Laravel's own server-tracked value, never
     * derived from a client header — so a malicious Referer must never
     * become the redirect target.
     */
    public function test_locale_switch_never_redirects_to_a_spoofed_referer(): void
    {
        $response = $this->withHeaders(['Referer' => 'https://evil.example.com/phishing'])
            ->get('/locale/ar');

        $response->assertRedirect();
        $this->assertStringNotContainsString('evil.example.com', $response->headers->get('Location'));
    }

    public function test_locale_persists_in_session_across_requests(): void
    {
        $this->get('/locale/ar');

        $this->get('/login');

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_guest_users_can_switch_locale(): void
    {
        // No actingAs() anywhere in this test — a genuinely guest request.
        $response = $this->get('/locale/ar');

        $response->assertRedirect();
        $this->assertSame('ar', session('locale'));
    }

    public function test_authenticated_users_can_switch_locale(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get('/locale/ar');

        $response->assertRedirect();
        $this->assertSame('ar', session('locale'));
    }

    public function test_app_locale_changes_correctly_after_switch(): void
    {
        $this->assertSame('en', app()->getLocale());

        $this->get('/locale/ar');
        $this->get('/login');

        $this->assertSame('ar', app()->getLocale());
    }

    // --- lang/dir attributes -----------------------------------------------

    public function test_arabic_renders_correct_lang_and_dir_attributes(): void
    {
        $this->get('/locale/ar');

        $response = $this->get('/login');

        $response->assertSee('lang="ar"', false);
        $response->assertSee('dir="rtl"', false);
    }

    public function test_english_renders_correct_lang_and_dir_attributes(): void
    {
        $this->get('/locale/en');

        $response = $this->get('/login');

        $response->assertSee('lang="en"', false);
        $response->assertSee('dir="ltr"', false);
    }

    // --- Enum labels --------------------------------------------------------

    public function test_enum_labels_translate_correctly_in_arabic(): void
    {
        app()->setLocale('ar');

        $this->assertSame('قيد الانتظار', OrderStatus::PENDING->label());
        $this->assertSame('تم التسليم', OrderStatus::DELIVERED->label());
        $this->assertSame('قيد الانتظار', PaymentStatus::PENDING->label());
        $this->assertSame('العميل', FeePayer::CUSTOMER->label());
        $this->assertSame('نقدًا', PaymentMethod::CASH->label());
        $this->assertSame('مشرف عام', UserRole::SUPER_ADMIN->label());

        app()->setLocale('en');

        $this->assertSame('Pending', OrderStatus::PENDING->label());
    }

    // --- Real pages, per audience -------------------------------------------

    public function test_admin_dashboard_renders_a_known_arabic_string(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->get('/locale/ar');

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('لوحة التحكم');
        $response->assertSee('إجمالي الطلبات');
    }

    public function test_public_tracking_renders_arabic(): void
    {
        $order = Order::factory()->create();
        $this->get('/locale/ar');

        $response = $this->get(route('track.show', $order->tracking_token));

        $response->assertOk();
        $response->assertSee('الحالة');
        $response->assertSee('سجل التتبع');
    }

    public function test_login_guest_page_renders_arabic(): void
    {
        $this->get('/locale/ar');

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('تسجيل الدخول');
    }

    // --- Driver Overview pluralization (Phase 8's trans_choice) ------------

    public function test_driver_overview_pluralization_works_in_english(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $driver = Driver::factory()->create();
        Order::factory()->delivered()->create(['driver_id' => $driver->id]);
        Order::factory()->delivered()->create(['driver_id' => $driver->id]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertSee('2 deliveries today');
    }

    public function test_driver_overview_pluralization_works_in_arabic_for_dual_form(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $driver = Driver::factory()->create();
        Order::factory()->delivered()->create(['driver_id' => $driver->id]);
        Order::factory()->delivered()->create(['driver_id' => $driver->id]);
        $this->get('/locale/ar');

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        // Arabic's dual form (exactly 2) is grammatically distinct from
        // both singular and plural — a real test of proper 6-form CLDR
        // pluralization, not just a 2-form English-style fallback.
        $response->assertSee('طلبان مسلَّمان اليوم');
    }

    public function test_driver_overview_pluralization_works_in_arabic_for_few_form(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $driver = Driver::factory()->create();
        Order::factory()->count(4)->delivered()->create(['driver_id' => $driver->id]);
        $this->get('/locale/ar');

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        // 3-10 is Arabic's "few" category — a distinct plural form from
        // both the dual (2) and the "many" (11-99) categories.
        $response->assertSee('4 طلبات مسلَّمة اليوم');
    }

    // --- Locale is presentation-only ----------------------------------------

    /**
     * The locale middleware/route never touch auth/role state — the
     * existing role:* middleware and Gate::before Super Admin bypass are
     * untouched by this phase. Same access-control outcome regardless of
     * which language is active.
     */
    public function test_locale_does_not_affect_authorization(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $this->get('/locale/ar');

        $response = $this->actingAs($dispatcher)->get('/admin/dashboard');

        $response->assertForbidden();
    }

    /**
     * Enum label() values are translated, but the underlying stored
     * OrderStatus value — and every real business rule keyed off it — is
     * completely untouched (spec §22: "do not modify enum values,
     * database values, comparisons, or state machine behavior"). An order
     * created while Arabic is active stores exactly the same status value
     * an English-locale order would.
     */
    public function test_locale_does_not_change_business_logic(): void
    {
        $this->get('/locale/ar');

        $order = Order::factory()->create();

        $this->assertSame('pending', $order->status->value);
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertTrue(Order::active()->whereKey($order->id)->exists());
    }
}
