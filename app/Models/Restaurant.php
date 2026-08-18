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
#[Fillable(['name', 'phone', 'address', 'pickup_latitude', 'pickup_longitude', 'is_active', 'opens_at', 'closes_at', 'closed_weekdays'])]
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
            // H:i (no seconds) both for display/form round-tripping and
            // for what gets written back to the TIME column — see
            // isOpenNow()/hoursLabel() below for how these are used.
            'opens_at' => 'datetime:H:i',
            'closes_at' => 'datetime:H:i',
            'closed_weekdays' => 'array',
        ];
    }

    /**
     * Sunday-first, matching Carbon's ->dayOfWeek/PHP's date('w') — the
     * indices stored in closed_weekdays. Plural (translation-ready) forms,
     * not composed from the singular at runtime, since concatenating a
     * translated fragment with a hardcoded "s" wouldn't hold up in a
     * language that pluralizes differently.
     *
     * @var array<int, string>
     */
    private const WEEKDAY_NAMES_PLURAL = ['Sundays', 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays'];

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

    /**
     * Whether the restaurant is within its configured opening hours right
     * now. A restaurant with no hours configured (either column null) is
     * treated as always open — that's the default for every restaurant
     * created before this feature existed, and for one whose hours a
     * staff member deliberately left blank. This is independent of
     * is_active: that column controls whether the restaurant is visible
     * to customers at all (see RestaurantController@show's 404), this
     * one only controls the "open now" badge/ordering gate on a
     * restaurant that IS visible.
     *
     * Compared as "H:i" strings rather than Carbon instances — both
     * columns cast to today's date via the 'datetime:H:i' cast, so a
     * string compare sidesteps any date-component mismatch and still
     * sorts correctly (zero-padded 24h time strings compare the same
     * lexicographically as chronologically).
     *
     * closed_weekdays is checked against TODAY's calendar day, not the
     * day the current opening window started — a deliberate
     * simplification for an overnight window that spans midnight into a
     * closed day (e.g. open Sun 18:00-02:00, closed Mondays): the
     * restaurant reads as closed the instant the calendar flips to
     * Monday, even though that's still "the same shift" that started
     * Sunday night. Precise enough for a single weekly day off; a full
     * per-day schedule would be the real fix if that ever matters.
     */
    public function isOpenNow(): bool
    {
        if ($this->isClosedToday()) {
            return false;
        }

        if (! $this->opens_at || ! $this->closes_at) {
            return true;
        }

        $now = now()->format('H:i');
        $opens = $this->opens_at->format('H:i');
        $closes = $this->closes_at->format('H:i');

        if ($opens === $closes) {
            // Equal opens_at/closes_at reads as "open all day" rather
            // than a zero-width window nothing could ever fall inside.
            return true;
        }

        if ($opens < $closes) {
            return $now >= $opens && $now < $closes;
        }

        // Overnight window (e.g. 18:00 opens_at, 02:00 closes_at) —
        // "now" is inside it if it's at/after opening OR before closing,
        // not between the two.
        return $now >= $opens || $now < $closes;
    }

    /**
     * Human-readable hours for display (e.g. "9:00 AM – 10:00 PM"), or
     * null when no hours are configured — callers fall back to their own
     * "hours not set" copy in that case.
     */
    public function hoursLabel(): ?string
    {
        if (! $this->opens_at || ! $this->closes_at) {
            return null;
        }

        return $this->opens_at->format('g:i A').' – '.$this->closes_at->format('g:i A');
    }

    /**
     * Whether today is one of this restaurant's configured days off —
     * independent of opens_at/closes_at, so it's checked first in
     * isOpenNow() rather than folded into the hours-window math.
     */
    public function isClosedToday(): bool
    {
        return $this->isClosedOnWeekday((int) now()->dayOfWeek);
    }

    /**
     * Whether the given weekday (0-6, Sunday-first) is one of this
     * restaurant's configured days off. Day-only — opens_at/closes_at is
     * a single window that applies the same every day (see this app's
     * "one daily window, not a per-day schedule" scope), so a day that
     * isn't a day off doesn't have its own separate hours to check;
     * hoursLabel() already covers that for every open day at once.
     */
    public function isClosedOnWeekday(int $day): bool
    {
        return in_array($day, $this->closed_weekdays ?? [], true);
    }

    /**
     * "Closed on Mondays" / "Closed on Sundays and Saturdays", or null
     * when no day off is configured.
     */
    public function closedWeekdaysLabel(): ?string
    {
        $days = $this->closed_weekdays ?? [];

        if (empty($days)) {
            return null;
        }

        $names = collect($days)
            ->sort()
            ->map(fn (int $day) => __(self::WEEKDAY_NAMES_PLURAL[$day] ?? ''))
            ->values();

        return __('Closed on :days', [
            'days' => $names->count() > 1
                ? $names->slice(0, -1)->implode(', ').' '.__('and').' '.$names->last()
                : $names->first(),
        ]);
    }
}
