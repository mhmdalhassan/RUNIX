<?php

namespace App\Http\Requests\Admin;

use App\Models\Restaurant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRestaurantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Restaurant::class);
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
            // Checkbox group: nothing checked means the key is absent
            // from the request entirely, which must normalize to an
            // empty array (not stay missing) so it still overwrites a
            // previously-set list on update.
            'closed_weekdays' => collect($this->input('closed_weekdays', []))
                ->map(fn ($day) => (int) $day)
                ->unique()
                ->values()
                ->all(),
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
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
            // Both or neither — an opening time with no closing time (or
            // vice versa) can't express a window. Equal values are fine
            // (Restaurant::isOpenNow() reads that as open all day), and
            // closes_at before opens_at is fine too (an overnight window,
            // e.g. 18:00-02:00), so there's no ordering rule here.
            'opens_at' => ['nullable', 'date_format:H:i', 'required_with:closes_at'],
            'closes_at' => ['nullable', 'date_format:H:i', 'required_with:opens_at'],
            'closed_weekdays' => ['array'],
            'closed_weekdays.*' => ['integer', 'between:0,6'],
        ];
    }
}
