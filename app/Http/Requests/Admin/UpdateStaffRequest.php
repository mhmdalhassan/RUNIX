<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Edits a staff account's name/email only. Role is intentionally fixed
 * at creation time (Phase 2 scope) to avoid a Dispatcher/Driver record
 * silently going out of sync with the role assigned when the account
 * was created — changing roles after the fact is deferred to a later
 * phase, once that has a real design (e.g. what happens to an existing
 * Driver profile if a user is re-roled to Dispatcher).
 */
class UpdateStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('user'));
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
        ];
    }
}
