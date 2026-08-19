<?php

namespace App\Http\Requests\Customer;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderFeedbackRequest extends FormRequest
{
    /**
     * The auth:customer middleware on this route only proves *a* customer
     * is logged in — this is what proves it's *this order's* customer.
     * Route-bound by tracking_token (see routes/web.php), same as the
     * public tracking page itself, so a wrong/foreign token still 404s
     * before authorization is ever reached.
     */
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $order->customer_id !== null
            && $order->customer_id === $this->user('customer')?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * DELIVERED-only and one-per-order are domain preconditions, not
     * plain field validation — SubmitDriverFeedbackService re-checks both
     * again inside its own transaction regardless (never trust a request
     * that's already passed validation to still be true a moment later).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Order $order */
            $order = $this->route('order');

            if ($order->status !== OrderStatus::DELIVERED) {
                $validator->errors()->add('order', __('Feedback can only be left once your order has been delivered.'));

                return;
            }

            if ($order->feedback()->exists()) {
                $validator->errors()->add('order', __('You have already left feedback for this order.'));
            }
        });
    }
}
