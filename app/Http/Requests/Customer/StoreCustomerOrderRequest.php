<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerOrderRequest extends FormRequest
{
    /**
     * The auth:customer + customer.profile.require-complete middleware
     * on this route already gate who can reach it at all.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => [
                'required',
                'integer',
                'distinct',
                // Every item must belong to the SAME restaurant as
                // restaurant_id above — a cart can only ever hold one
                // pickup location. CreateCustomerOrderService re-checks
                // availability on top of this at write time (a menu item
                // can go unavailable between this validation pass and
                // the transaction a moment later).
                Rule::exists('menu_items', 'id')->where('restaurant_id', $this->input('restaurant_id')),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],

            'delivery_address' => ['required', 'string', 'max:2000'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
