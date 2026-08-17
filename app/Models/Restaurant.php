<?php

namespace App\Models;

use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A restaurant a dispatcher/admin manages the menu for (Admin/Dispatcher
 * CRUD only, this phase — see App\Http\Controllers\Admin\RestaurantController).
 * pickup_latitude/pickup_longitude deliberately mirror Order's own pickup
 * coordinate columns (same name, same decimal(10,7) precision) so a future
 * "create an order from this restaurant" flow can copy them straight
 * across for App\Services\Orders\EligibleDriverFinder's proximity ranking.
 */
#[Fillable(['name', 'phone', 'address', 'pickup_latitude', 'pickup_longitude', 'is_active'])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<MenuCategory, $this>
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * @param  Builder<Restaurant>  $query
     * @return Builder<Restaurant>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * logo_path is deliberately absent from #[Fillable] above — only ever
     * written by the controller via App\Services\Uploads\
     * StoreUploadedPhotoService, never mass-assigned from form input
     * directly (same trust boundary as Order's tracking_token/order_number).
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
