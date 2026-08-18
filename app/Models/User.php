<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'restaurant_id', 'status_preview_weekday'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'status_preview_weekday' => 'integer',
        ];
    }

    /**
     * Which weekday (0-6, Sunday-first) this account currently wants to
     * preview a restaurant's open/closed status for on the admin
     * restaurant page — falls back to today when nothing's been chosen
     * yet. See Admin\RestaurantStatusPreviewController for where it's set.
     */
    public function statusPreviewWeekday(): int
    {
        return $this->status_preview_weekday ?? (int) now()->dayOfWeek;
    }

    /**
     * The driver profile linked to this user, if the user is a driver.
     *
     * @return HasOne<Driver, $this>
     */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    /**
     * The restaurant this account manages — only ever set for a
     * RESTAURANT_ADMIN (see RestaurantPolicy/MenuCategoryPolicy/
     * MenuItemPolicy for how it gates access); null for every other role.
     *
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    public function isDispatcher(): bool
    {
        return $this->role === UserRole::DISPATCHER;
    }

    public function isDriver(): bool
    {
        return $this->role === UserRole::DRIVER;
    }

    public function isRestaurantAdmin(): bool
    {
        return $this->role === UserRole::RESTAURANT_ADMIN;
    }

    /**
     * Scope to accounts manageable through the Staff Management UI.
     *
     * RunIX has no Super Admin management screen in V1 — Super Admin
     * accounts are deliberately excluded here so they can never be
     * listed, edited, deactivated, or password-reset through
     * `/admin/users`, no matter who is asking.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('role', [UserRole::DISPATCHER, UserRole::DRIVER, UserRole::RESTAURANT_ADMIN]);
    }
}
