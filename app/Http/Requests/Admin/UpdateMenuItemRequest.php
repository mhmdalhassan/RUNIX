<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('menu_item'));
    }

    /**
     * Normalize the checkbox inputs so an unchecked box is explicitly
     * false rather than silently missing from validated().
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_available' => $this->boolean('is_available'),
            'remove_photo' => $this->boolean('remove_photo'),
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
            'remove_photo' => ['boolean'],
            // Same data-integrity guard as StoreMenuItemRequest, scoped to
            // this item's own restaurant (shallow route — no {restaurant}
            // segment here, just {menu_item}).
            'menu_category_id' => [
                'required',
                Rule::exists('menu_categories', 'id')->where('restaurant_id', $this->route('menu_item')->restaurant_id),
            ],
        ];
    }
}
