<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Both the staff-facing CRM record (unchanged — Admin\CustomerController
 * still creates these with no password, phone required at that point of
 * entry) AND, as of the customer-auth pass, the customer's own login
 * identity on the separate `customer` guard (see config/auth.php). A row
 * with `password === null` is "unclaimed" — created by a dispatcher from
 * a phone order, nobody's registered against it yet. See
 * App\Services\Customers\CompleteCustomerProfileService for the one place
 * an unclaimed row gets claimed by a newly-registered account instead of
 * that account staying a separate duplicate row.
 */
#[Fillable(['name', 'phone', 'address', 'email', 'password', 'notes', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
