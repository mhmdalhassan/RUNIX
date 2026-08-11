<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('driver.dashboard', [
            'user' => $request->user(),
            'driver' => $request->user()->driver,
        ]);
    }
}
