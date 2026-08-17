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
     * Start of the selected range. The end is always "now" (see
     * DashboardController) — nobody filters into the future.
     */
    public function start(): Carbon
    {
        return match ($this) {
            self::TODAY => today(),
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
        };
    }
}
