<?php

namespace App\Http\Requests\Admin;

use App\Models\MenuItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', MenuItem::class);
    }

    /**
     * Normalize the checkbox input: an unchecked box sends no value at
     * all, which would otherwise leave the key absent from validated()
     * instead of explicitly false.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_available' => $this->boolean('is_available', default: true),
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
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_available' => ['boolean'],
            'photo' => ['nullable', 'image', 'max:2048'],
            // A data-integrity guard, not an authorization check: stops a
            // category id belonging to a *different* restaurant being
            // posted in against this one's create form.
            'menu_category_id' => [
                'required',
                Rule::exists('menu_categories', 'id')->where('restaurant_id', $this->route('restaurant')?->id),
            ],
        ];
    }
}
