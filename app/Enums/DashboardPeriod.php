<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * The date-range filter on the admin dashboard (Admin\DashboardController).
 * Every "today" figure there — Total Orders, Delivered count, Revenue,
 * Driver Earnings, Expenses, Net Profit, Driver Overview — is actually
 * scoped to whichever of these is selected via the `?period=` query
 * param, `start()` through "now".
 *
 * TODAY is the default and reproduces the dashboard's original
 * (pre-filter) behavior exactly, so callers/tests that never pass a
 * `period` param keep seeing identical numbers under the same
 * *Today-suffixed view keys — those keys never got renamed, they just
 * became period-aware.
 */
enum DashboardPeriod: string
{
    case TODAY = 'today';
    case WEEK = 'week';
    case MONTH = 'month';
    case YEAR = 'year';
    // A single picked-by-hand calendar day, not a relative range — see
    // Admin\DashboardController's own docblock on why this one has no
    // meaningful start()/needs the `date` query param instead, and
    // DashboardPeriod::isCustom()/presets() for how the view keeps it
    // out of the ordinary tab loop.
    case CUSTOM = 'custom';

    /**
     * Parses the `period` query param, falling back to TODAY for a
     * missing or unrecognized value — an unknown filter shouldn't 500
     * the dashboard, it should just show today's numbers.
     */
    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::TODAY;
    }

    /**
     * The relative-range presets shown as ordinary tab links — CUSTOM
     * excluded, since it's rendered as a date-picker form instead (see
     * admin/dashboard.blade.php).
     *
     * @return list<self>
     */
    public static function presets(): array
    {
        return array_values(array_filter(self::cases(), fn (self $period) => $period !== self::CUSTOM));
    }

    public function isCustom(): bool
    {
        return $this === self::CUSTOM;
    }

    /**
     * Start of the selected range. The end is always "now" (see
     * DashboardController) — nobody filters into the future. Not
     * meaningful for CUSTOM — DashboardController never calls this for
     * that case, computing the picked day's own start/end from the
     * `date` query param instead; the arm below only exists so this
     * match stays exhaustive.
     */
    public function start(): Carbon
    {
        return match ($this) {
            self::TODAY, self::CUSTOM => today(),
            self::WEEK => now()->startOfWeek(),
            self::MONTH => now()->startOfMonth(),
            self::YEAR => now()->startOfYear(),
        };
    }

    /**
     * Short label for the filter tabs and the section heading.
     */
    public function label(): string
    {
        return match ($this) {
            self::TODAY => __('Today'),
            self::WEEK => __('This Week'),
            self::MONTH => __('This Month'),
            self::YEAR => __('This Year'),
            self::CUSTOM => __('Custom Day'),
        };
    }

    /**
     * Breakdown card title. A full phrase per period — not
     * "{$this->label()} Breakdown" — so translations stay natural
     * sentences instead of concatenated fragments.
     */
    public function breakdownTitle(): string
    {
        return match ($this) {
            self::TODAY => __("Today's Breakdown"),
            self::WEEK => __("This Week's Breakdown"),
            self::MONTH => __("This Month's Breakdown"),
            self::YEAR => __("This Year's Breakdown"),
            self::CUSTOM => __('Breakdown for the Selected Day'),
        };
    }

    /**
     * Total Orders stat card caption.
     */
    public function createdCaption(): string
    {
        return match ($this) {
            self::TODAY => __('Created today'),
            self::WEEK => __('Created this week'),
            self::MONTH => __('Created this month'),
            self::YEAR => __('Created this year'),
            self::CUSTOM => __('Created on the selected day'),
        };
    }

    /**
     * Revenue stat card caption.
     */
    public function deliveredCaption(): string
    {
        return match ($this) {
            self::TODAY => __('Delivered today'),
            self::WEEK => __('Delivered this week'),
            self::MONTH => __('Delivered this month'),
            self::YEAR => __('Delivered this year'),
            self::CUSTOM => __('Delivered on the selected day'),
        };
    }

    /**
     * trans_choice key for the Driver Overview per-driver caption — one
     * full phrase per period (not "{count} deliveries {$label}") for
     * the same translation reason as breakdownTitle().
     */
    public function deliveryCountPhrase(): string
    {
        return match ($this) {
            self::TODAY => ':count delivery today|:count deliveries today',
            self::WEEK => ':count delivery this week|:count deliveries this week',
            self::MONTH => ':count delivery this month|:count deliveries this month',
            self::YEAR => ':count delivery this year|:count deliveries this year',
            self::CUSTOM => ':count delivery on this day|:count deliveries on this day',
        };
    }
}
