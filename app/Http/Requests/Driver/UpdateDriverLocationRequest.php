<?php

namespace App\Http\Requests\Driver;

use App\Models\Driver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Structural validity only (Phase 5 §1) — is this a real lat/lng/accuracy
 * shape. The accuracy *ceiling* (worse than N meters gets silently
 * dropped rather than rejected) is a business rule, not a validation
 * rule, so it lives in App\Services\Drivers\UpdateDriverLocationService
 * instead, not here.
 */
class UpdateDriverLocationRequest extends FormRequest
{
    /**
     * Mirrors UpdateOrderRequest's Gate::allows(..., $this->route(...))
     * pattern — the difference is this route has no {driver} parameter
     * (it's always the authenticated driver's own record, same scoping
     * AvailabilityController already relies on), so the Driver instance
     * comes from the user relation instead of route-model-binding. The
     * 404-for-no-driver-record case also mirrors AvailabilityController.
     */
    public function authorize(): bool
    {
        $driver = $this->user()->driver;

        abort_if($driver === null, 404);

        return Gate::allows('update', $driver);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * The authenticated driver's own record — already confirmed non-null
     * by authorize() above, which always runs before the controller sees
     * this request, so the controller doesn't need to re-check or re-query.
     */
    public function driver(): Driver
    {
        return $this->user()->driver;
    }
}
