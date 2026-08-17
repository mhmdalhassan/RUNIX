<?php

namespace App\Services\Customers;

use App\Exceptions\CustomerProfileAlreadyCompletedException;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The one place a newly-registered Customer's phone number gets checked
 * against existing, unclaimed (password === null) rows created by a
 * dispatcher from a phone order — and, if one matches, merged into
 * instead of left as a duplicate. See this app's own docblock convention
 * for small, independently-testable write services, e.g.
 * App\Services\Orders\ClaimOrderForDriverService.
 */
class CompleteCustomerProfileService
{
    /**
     * @throws CustomerProfileAlreadyCompletedException if $registered's own
     *                                                  row is already gone (a double-submitted request) and the
     *                                                  session can't even fall back to who's currently authenticated.
     */
    public function complete(Customer $registered, string $phone, ?string $address): Customer
    {
        return DB::transaction(function () use ($registered, $phone, $address) {
            // Re-lock the registrant's own row: if a duplicate/retried
            // submission of this same form already merged it away
            // (double-click, browser retry), this comes back null —
            // treat that as already-completed rather than erroring.
            $current = Customer::whereKey($registered->id)->lockForUpdate()->first();

            if ($current === null) {
                $stillAuthenticated = Auth::guard('customer')->user();

                if ($stillAuthenticated instanceof Customer) {
                    return $stillAuthenticated;
                }

                throw new CustomerProfileAlreadyCompletedException;
            }

            // Only ever match a row with NO login credentials yet — a
            // dispatcher-created, phone-order-only row. Never match a
            // row that already has a password: that would mean silently
            // attaching this registrant's session onto someone else's
            // real account just by knowing their phone number — an
            // account-takeover vector, not a data-quality convenience.
            // lockForUpdate() blocks a second, genuinely concurrent
            // complete-profile submission (e.g. two tabs) targeting the
            // same legacy row until the first commits; the second then
            // re-reads and finds it already claimed, falling through to
            // the "no match" branch below instead of racing.
            $match = Customer::query()
                ->where('phone', $phone)
                ->whereNull('password')
                ->whereKeyNot($current->id)
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if ($match === null) {
                $current->update(['phone' => $phone, 'address' => $address]);

                return $current;
            }

            // Capture what's moving BEFORE $current is deleted — email
            // has a unique DB constraint, so assigning it to $match while
            // $current's row (still holding that same value) exists would
            // itself violate that constraint. Delete first, claim second.
            $email = $current->email;
            $password = $current->password; // already hashed — copy verbatim, never re-hash
            $emailVerifiedAt = $current->email_verified_at;

            // Defensive re-point: nothing in this pass creates an Order
            // against a customer between registration and profile
            // completion, so this shouldn't match anything today — cheap
            // and correct to have in place before a future phase changes
            // that.
            Order::where('customer_id', $current->id)->update(['customer_id' => $match->id]);

            $current->delete();

            // Merge: move ONLY the new login identity onto the
            // pre-existing (older, staff-created) row. Its name/notes/
            // is_active/order history are the authoritative real-world
            // facts a dispatcher already recorded — those stay untouched.
            $match->forceFill([
                'email' => $email,
                'password' => $password,
                'email_verified_at' => $emailVerifiedAt,
                'phone' => $phone,
                'address' => $address ?? $match->address,
            ])->save();

            return $match->refresh();
        });
    }
}
