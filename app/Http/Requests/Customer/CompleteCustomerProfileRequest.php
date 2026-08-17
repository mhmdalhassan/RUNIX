<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteCustomerProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization here is just "must be logged in as a customer" —
     * already enforced by the `auth:customer` route middleware, so this
     * request has nothing further to check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
        ];
    }
}
