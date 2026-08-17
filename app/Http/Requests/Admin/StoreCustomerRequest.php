<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Customer::class);
    }

    /**
     * Normalize the checkbox input: an unchecked box sends no value at
     * all, which would otherwise leave the key absent from validated()
     * instead of explicitly false.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', default: true),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            // email is a unique DB column as of the customer-auth pass —
            // it doubles as a customer's login identifier now, not just
            // a contact detail — so this must reject duplicates with a
            // clean validation error rather than a raw QueryException.
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:customers,email'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
