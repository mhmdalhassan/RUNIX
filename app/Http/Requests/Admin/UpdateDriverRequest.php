<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('driver'));
    }

    /**
     * Normalize checkbox inputs so an unchecked box is explicitly false
     * rather than silently missing from validated().
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_online' => $this->boolean('is_online'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $driver = $this->route('driver');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($driver->user_id)],
            'phone' => ['required', 'string', 'max:255', Rule::unique('drivers', 'phone')->ignore($driver->id)],
            'is_active' => ['boolean'],
            'is_online' => ['boolean'],
        ];
    }
}
