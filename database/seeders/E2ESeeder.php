<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deliberately separate from DatabaseSeeder — this is fixture data for the
 * Playwright suite (e2e/), never run against the dev or production
 * database. Playwright's global-setup runs `migrate:fresh --seed
 * --seeder=Database\\Seeders\\E2ESeeder` against a dedicated `runix_e2e`
 * database (see playwright.config.ts), so every spec starts from this
 * exact, known state.
 *
 * Three driver accounts, not one, because the flow spec needs it: Driver A
 * and Driver B are both online/active so an AVAILABLE order offers to both
 * (covering accept + reject + isolation in one realistic scenario), while
 * Driver C exists solely for the online/offline toggle test so flipping it
 * can never disturb A/B's eligibility for the shared offer flow.
 */
class E2ESeeder extends Seeder
{
    use WithoutModelEvents;

    public const SUPER_ADMIN_EMAIL = 'e2e-admin@runix.test';

    public const DISPATCHER_EMAIL = 'e2e-dispatcher@runix.test';

    public const DRIVER_A_EMAIL = 'e2e-driver-a@runix.test';

    public const DRIVER_B_EMAIL = 'e2e-driver-b@runix.test';

    public const DRIVER_C_EMAIL = 'e2e-driver-c@runix.test';

    public const PASSWORD = 'RunIX!E2E2026';

    public function run(): void
    {
        $password = Hash::make(self::PASSWORD);

        User::factory()->superAdmin()->create([
            'name' => 'E2E Super Admin',
            'email' => self::SUPER_ADMIN_EMAIL,
            'password' => $password,
        ]);

        User::factory()->dispatcher()->create([
            'name' => 'E2E Dispatcher',
            'email' => self::DISPATCHER_EMAIL,
            'password' => $password,
        ]);

        $driverAUser = User::factory()->driver()->create([
            'name' => 'E2E Driver A',
            'email' => self::DRIVER_A_EMAIL,
            'password' => $password,
        ]);
        Driver::factory()->online()->create([
            'user_id' => $driverAUser->id,
            'phone' => '+96170000001',
        ]);

        $driverBUser = User::factory()->driver()->create([
            'name' => 'E2E Driver B',
            'email' => self::DRIVER_B_EMAIL,
            'password' => $password,
        ]);
        Driver::factory()->online()->create([
            'user_id' => $driverBUser->id,
            'phone' => '+96170000002',
        ]);

        $driverCUser = User::factory()->driver()->create([
            'name' => 'E2E Driver C',
            'email' => self::DRIVER_C_EMAIL,
            'password' => $password,
        ]);
        Driver::factory()->online()->create([
            'user_id' => $driverCUser->id,
            'phone' => '+96170000003',
        ]);

        Customer::factory()->create([
            'name' => 'E2E Customer',
            'phone' => '+96171000000',
            'email' => 'e2e-customer@runix.test',
            'is_active' => true,
        ]);
    }
}
