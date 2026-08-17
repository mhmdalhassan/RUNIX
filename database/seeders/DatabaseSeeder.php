<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    
    public const SUPER_ADMIN_EMAIL = 'admin@runix.test';

    public const SUPER_ADMIN_PASSWORD = 'RunIX!SuperAdmin2026';

    public function run(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'name' => 'RunIX Super Admin',
            'email' => self::SUPER_ADMIN_EMAIL,
            'password' => Hash::make(self::SUPER_ADMIN_PASSWORD),
        ]);

        $dispatcher = User::factory()->dispatcher()->create([
            'name' => 'RunIX Dispatcher',
            'email' => 'dispatcher@runix.test',
        ]);

        $driverUser = User::factory()->driver()->create([
            'name' => 'RunIX Driver',
            'email' => 'driver@runix.test',
        ]);

        Driver::factory()->create([
            'user_id' => $driverUser->id,
            'phone' => '+96170000000',
        ]);

        $this->seedDemoOperationalData($dispatcher);
    }

    /**
     * A small, realistic slice of Order-Core demo data — 10 customers, 5
     * extra drivers, 20 orders spread across every status — so a fresh
     * RunIX install has something to look at immediately. Guarded on
     * Customer::count() so re-running `db:seed` never duplicates it.
     */
    private function seedDemoOperationalData(User $dispatcher): void
    {
        if (Customer::count() > 0) {
            return;
        }

        $customers = Customer::factory()->count(10)->create();
        $drivers = Driver::factory()->count(5)->create();

        // Every OrderStatus is represented at least once.
        $plan = [
            'pending' => 3,
            'available' => 2,
            'accepted' => 4,
            'pickedUp' => 3,
            'onTheWay' => 3,
            'delivered' => 3,
            'cancelled' => 1,
            'failed' => 1,
        ];

        foreach ($plan as $state => $count) {
            $factory = Order::factory()->recycle($customers)->recycle($drivers)->count($count);
            $orders = $state === 'pending' ? $factory->create() : $factory->{$state}()->create();

            foreach ($orders as $order) {
                // Every order originates in PENDING regardless of which
                // state the factory fast-forwarded it to — the origin
                // history row must say so too, not
                // OrderTransitionService::recordInitial()'s usual
                // "whatever $order->status currently is" (that method
                // assumes it's called at true creation time, which isn't
                // true here; a factory-jumped order would otherwise get a
                // self-contradictory trail, e.g. "created as Accepted"
                // immediately followed by "Pending -> Accepted").
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => OrderStatus::PENDING,
                    'changed_by' => $dispatcher->id,
                    'note' => null,
                ]);

                if ($order->status !== OrderStatus::PENDING) {
                    // Minimal trail (initial + final, not every
                    // intermediate hop) — keeps seed data small per §22
                    // while still giving the order-detail timeline
                    // something real to render.
                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'from_status' => OrderStatus::PENDING,
                        'to_status' => $order->status,
                        'changed_by' => $dispatcher->id,
                        'note' => null,
                    ]);
                }
            }
        }
    }
}
