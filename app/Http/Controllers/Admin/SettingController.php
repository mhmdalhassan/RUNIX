<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * A single-page settings form, not a CRUD resource — Setting itself is a
 * plain key/value store (see its own docblock), so there's one "document"
 * to edit, not a list of records. Currently just whatsapp_number; adding
 * a field later is a new input on the form + a new key here, no
 * migration.
 */
class SettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'whatsappNumber' => Setting::get('whatsapp_number'),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        // An emptied field stores as null, not '' — Setting::get()'s
        // default-value fallback only kicks in for a real null.
        Setting::set('whatsapp_number', $request->validated('whatsapp_number') ?: null);

        return redirect()->route('admin.settings.edit')->with('status', 'Settings updated.');
    }
}
