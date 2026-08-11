<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.dashboard', [
            'user' => $request->user(),
            // Read-only counts against tables that already exist. Order/
            // revenue figures aren't real yet (Phase 3) — the dashboard
            // view renders those tiles as placeholders instead of
            // querying a table that isn't there.
            'stats' => [
                'active_drivers' => Driver::where('is_active', true)->count(),
                'online_drivers' => Driver::where('is_online', true)->count(),
                'customers' => Customer::where('is_active', true)->count(),
                'staff' => User::staff()->count(),
            ],
        ]);
    }
}
