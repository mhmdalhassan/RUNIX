<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * System settings are Super Admin only — no per-instance policy needed
 * (there's no owned resource to check against, unlike Driver/Order/etc.),
 * same reasoning Admin\DashboardController uses for relying on the
 * route's role:super_admin middleware alone rather than a Gate check;
 * this only adds the role check explicitly since a FormRequest's
 * authorize() runs regardless of which route reaches it.
 */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'whatsapp_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
