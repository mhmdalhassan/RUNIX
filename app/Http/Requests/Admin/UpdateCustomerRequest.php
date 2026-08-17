<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('customer'));
    }

    /**
     * Normalize the checkbox input so an unchecked box is explicitly
     * false rather than silently missing from validated().
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
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
            // ignore this customer's own current row so saving the form
            // unchanged doesn't trip over its own email.
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($this->route('customer'))],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
