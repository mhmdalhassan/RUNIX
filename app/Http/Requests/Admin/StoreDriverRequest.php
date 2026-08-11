<?php

namespace App\Http\Requests\Admin;

use App\Models\Driver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules;

class StoreDriverRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Driver::class);
    }

    /**
     * Normalize checkbox inputs: an unchecked checkbox sends no value at
     * all, which would otherwise make these keys silently absent from
     * validated() instead of explicitly false.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', default: true),
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => ['required', 'string', 'max:255', 'unique:drivers,phone'],
            'is_active' => ['boolean'],
            'is_online' => ['boolean'],
        ];
    }
}
