<?php

namespace App\Http\Requests\Admin;

use App\Enums\FeePayer;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Order::class);
    }

    /**
     * Normalize checkbox/select inputs the same way StoreDriverRequest
     * does, so absent keys are explicit rather than silently missing.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'driver_earning_override' => $this->boolean('driver_earning_override'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $codEnabled = (bool) config('runix.cod_enabled');

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],

            'pickup_address' => ['required', 'string'],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'delivery_address' => ['required', 'string'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'delivery_fee' => ['required', 'numeric', 'min:0'],
            'fee_payer' => ['nullable', Rule::enum(FeePayer::class)],

            // COD fields are validated whenever present, but
            // CreateOrderService still forces them to 0 server-side when
            // config('runix.cod_enabled') is false — this rule doesn't
            // require them, only shapes them if the (hidden) UI sent
            // something anyway.
            'merchant_amount' => [$codEnabled ? 'nullable' : 'prohibited', 'numeric', 'min:0'],
            'cod_amount' => [$codEnabled ? 'nullable' : 'prohibited', 'numeric', 'min:0'],

            'driver_earning' => ['required', 'numeric', 'min:0'],
            'driver_earning_override' => ['boolean'],
            'driver_earning_override_reason' => ['nullable', 'string', 'max:1000'],

            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],

            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The driver-earning-override cross-field/role rule (spec §5) needs
     * the authenticated user, so it lives here rather than in a plain
     * field rule. Phase 6 §1 adds the pickup-coordinate pair rule
     * alongside it — both present or both absent, never a partial pair.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $fee = (float) $this->input('delivery_fee', 0);
            $earning = (float) $this->input('driver_earning', 0);

            if ($earning <= $fee) {
                return;
            }

            if (! $this->user()->isSuperAdmin()) {
                $validator->errors()->add(
                    'driver_earning',
                    __('A driver earning above the delivery fee can only be approved by a Super Admin.'),
                );

                return;
            }

            if (! $this->boolean('driver_earning_override')) {
                $validator->errors()->add(
                    'driver_earning_override',
                    __('Confirm the override to set a driver earning above the delivery fee.'),
                );
            }

            if (trim((string) $this->input('driver_earning_override_reason', '')) === '') {
                $validator->errors()->add(
                    'driver_earning_override_reason',
                    __('A reason is required when the driver earning exceeds the delivery fee.'),
                );
            }
        });

        $validator->after($this->assertPickupCoordinatePairing(...));
    }

    /**
     * Phase 6 §1 — pickup_latitude/pickup_longitude must arrive together
     * or not at all. Individually-nullable field rules can't express
     * that, so it's enforced here the same way the driver-earning-override
     * cross-field rule above is.
     */
    private function assertPickupCoordinatePairing(Validator $validator): void
    {
        $hasLatitude = $this->filled('pickup_latitude');
        $hasLongitude = $this->filled('pickup_longitude');

        if ($hasLatitude === $hasLongitude) {
            return;
        }

        $validator->errors()->add(
            $hasLatitude ? 'pickup_longitude' : 'pickup_latitude',
            __('Pickup latitude and longitude must both be provided together, or both left blank.'),
        );
    }
}
