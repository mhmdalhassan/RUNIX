<?php

namespace Tests\Feature\Admin;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * admin/expenses — create + list only, Super Admin only. Real money
 * leaving the business, same sensitivity level as Staff Management.
 */
class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => 45.50,
            'description' => 'Fuel for van 3',
            'date' => today()->toDateString(),
        ], $overrides);
    }

    // --- Access control -----------------------------------------------

    public function test_super_admin_can_view_the_expenses_page(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.expenses.index'))->assertOk();
    }

    public function test_super_admin_can_view_the_record_expense_form(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.expenses.create'))->assertOk();
    }

    public function test_dispatcher_cannot_view_the_expenses_page(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($dispatcher)->get(route('admin.expenses.index'))->assertForbidden();
    }

    public function test_dispatcher_cannot_record_an_expense(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.expenses.store'), $this->validPayload());

        $response->assertForbidden();
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_driver_cannot_view_the_expenses_page(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)->get(route('admin.expenses.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.expenses.index'))->assertRedirect(route('login'));
    }

    // --- Recording an expense -----------------------------------------

    public function test_super_admin_can_record_an_expense(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.expenses.store'), $this->validPayload([
            'amount' => 45.5,
            'description' => 'Fuel for van 3',
        ]));

        $response->assertRedirect(route('admin.expenses.index'));
        $this->assertDatabaseCount('expenses', 1);

        $expense = Expense::firstOrFail();
        $this->assertSame('45.50', $expense->amount);
        $this->assertSame('Fuel for van 3', $expense->description);
        $this->assertSame($admin->id, $expense->recorded_by);
    }

    public function test_amount_must_be_a_positive_number(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.expenses.store'), $this->validPayload([
            'amount' => 0,
        ]));

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_description_is_required(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.expenses.store'), $this->validPayload([
            'description' => '',
        ]));

        $response->assertSessionHasErrors('description');
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_date_cannot_be_in_the_future(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.expenses.store'), $this->validPayload([
            'date' => today()->addDay()->toDateString(),
        ]));

        $response->assertSessionHasErrors('date');
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_expense_can_be_backdated(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.expenses.store'), $this->validPayload([
            'date' => today()->subDays(3)->toDateString(),
        ]))->assertRedirect();

        $expense = Expense::firstOrFail();
        $this->assertSame(today()->subDays(3)->toDateString(), $expense->date->toDateString());
    }

    public function test_expenses_list_shows_recorded_expenses(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Expense::factory()->create(['description' => 'Vehicle maintenance', 'amount' => 120]);

        $response = $this->actingAs($admin)->get(route('admin.expenses.index'));

        $response->assertOk();
        $response->assertSee('Vehicle maintenance');
        $response->assertSee('120.00');
    }

    public function test_expenses_list_shows_the_empty_state_with_none_recorded(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get(route('admin.expenses.index'));

        $response->assertOk();
        $response->assertSee('No expenses recorded yet');
    }

    // --- Expense::today() scope --------------------------------------------

    public function test_today_scope_only_includes_todays_expenses(): void
    {
        Expense::factory()->create(['date' => today(), 'amount' => 10]);
        Expense::factory()->create(['date' => today()->subDay(), 'amount' => 999]);

        $this->assertEquals(10.0, (float) Expense::today()->sum('amount'));
    }
}
