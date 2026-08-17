<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\RegisterCustomerRequest;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * No staff equivalent — self-registration is intentionally disabled for
 * staff (see routes/auth.php's own comment); customers are the one actor
 * type in this app that registers themselves.
 */
class RegisteredCustomerController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('customer.auth.register');
    }

    /**
     * Handle an incoming registration request. Deliberately does not
     * collect phone/address here — those come after, on the
     * complete-profile step, which is also where a matching
     * dispatcher-created Customer row (if any) gets linked instead of
     * this leaving a duplicate. See
     * App\Services\Customers\CompleteCustomerProfileService.
     */
    public function store(RegisterCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'is_active' => true,
        ]);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('customer.complete-profile.edit');
    }
}
