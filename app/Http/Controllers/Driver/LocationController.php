<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\UpdateDriverLocationRequest;
use App\Services\Drivers\UpdateDriverLocationService;
use Illuminate\Http\JsonResponse;

/**
 * Phase 5 §1/§4. The app's first pure-JSON mutation endpoint — every other
 * mutation route redirects with a flashed status, but this one is driven
 * by resources/js/runix/driver-location.js's watchPosition() callback,
 * not a form submit, so a redirect has nothing to navigate. Kept minimal
 * on purpose: a bare success acknowledgement, no response body the client
 * needs to act on.
 */
class LocationController extends Controller
{
    public function update(UpdateDriverLocationRequest $request, UpdateDriverLocationService $service): JsonResponse
    {
        $service->update($request->driver(), $request->validated());

        return response()->json(['status' => 'ok']);
    }
}
