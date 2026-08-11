<?php

namespace App\Http\Requests\Admin;

use App\Enums\FeePayer;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('order'));
    }

    /**
     * Normalizes the override checkbox only while it's actually editable.
     * Merging in a `false` while locked would break the `prohibited` rule
     * below — `prohibited` only accepts null/''/empty-array as "absent",
     * and boolean false doesn't qualify, so it would reject a locked
     * order's own update every time.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->isLocked()) {
            $this->merge([
                'driver_earning_override' => $this->boolean('driver_earning_override'),
            ]);
        }
    }

    /**
     * True once the order has moved past PENDING/AVAILABLE — spec §16:
     * customer/pickup/delivery/delivery_fee/driver_earning stop being
     * casually editable once real operational progress has been made.
     */
    private function isLocked(): bool
    {
        $order = $this->route('order');

        return ! in_array($order->status, [OrderStatus::PENDING, OrderStatus::AVAILABLE], strict: true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $locked = $this->isLocked();
        $codEnabled = (bool) config('runix.cod_enabled');

        return [
            'pickup_address' => Rule::when($locked, ['prohibited'], ['required', 'string']),
            'delivery_address' => Rule::when($locked, ['prohibited'], ['required', 'string']),
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'delivery_fee' => Rule::when($locked, ['prohibited'], ['required', 'numeric', 'min:0']),
            'fee_payer' => ['nullable', Rule::enum(FeePayer::class)],

            'merchant_amount' => [$codEnabled ? 'nullable' : 'prohibited', 'numeric', 'min:0'],
            'cod_amount' => [$codEnabled ? 'nullable' : 'prohibited', 'numeric', 'min:0'],

            'driver_earning' => Rule::when($locked, ['prohibited'], ['required', 'numeric', 'min:0']),
            'driver_earning_override' => Rule::when($locked, ['prohibited'], ['boolean']),
            'driver_earning_override_reason' => Rule::when($locked, ['prohibited'], ['nullable', 'string', 'max:1000']),

            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],

            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Same driver-earning-override cross-field/role rule as
     * StoreOrderRequest — skipped entirely while locked, since the
     * `prohibited` rules above already keep those fields out of a locked
     * update altogether.
     */
    public function withValidator(Validator $validator): void
    {
        if ($this->isLocked()) {
            return;
        }

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
    }
}
