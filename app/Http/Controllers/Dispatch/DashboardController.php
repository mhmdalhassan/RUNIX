<?php

namespace App\Http\Controllers\Dispatch;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Driver::class);

        return view('dispatch.dashboard', [
            'user' => $request->user(),
            'drivers' => Driver::with('user')->latest()->get(),
        ]);
    }
}
