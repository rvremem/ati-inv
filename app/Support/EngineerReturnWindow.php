<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The after-hours window in which Engineers may return parts.
 *
 * Outside office hours there is often no Admin on site to book a part back
 * in, so Engineers are let in to do it themselves. Their returns are not
 * final: each one waits as 'Pending Return' until an Admin or Super Admin
 * approves it (see ReceivingController::returnPartsApprove()).
 *
 * Settings live under config('app.engineer_returns'). The clock is read in
 * the configured timezone, not app.timezone — the app runs in UTC, and
 * "5 PM" means 5 PM in the branch, not in Greenwich.
 */
class EngineerReturnWindow
{
    public static function enabled(): bool
    {
        return (bool) config('app.engineer_returns.enabled', true);
    }

    public static function timezone(): string
    {
        return config('app.engineer_returns.timezone', 'Asia/Manila');
    }

    /** Hour (0-23) the weekday window opens, e.g. 17 for 5:00 PM. */
    private static function opensAt(): int
    {
        return (int) config('app.engineer_returns.weekday_opens_at', 17);
    }

    /** Hour (0-23) the weekday window closes, e.g. 8 for 8:00 AM (open until 7:59). */
    private static function closesAt(): int
    {
        return (int) config('app.engineer_returns.weekday_closes_at', 8);
    }

    /**
     * Whether the window is open at $at (now when omitted).
     *
     * Weekends are open all day. On a weekday it is open from opensAt until
     * midnight, and from midnight until closesAt — so Monday 00:00-07:59
     * counts as open too, continuing Sunday night.
     */
    public static function isOpen(?Carbon $at = null): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $local = ($at ?? Carbon::now())->copy()->setTimezone(self::timezone());

        if ($local->isWeekend()) {
            return true;
        }

        return $local->hour >= self::opensAt() || $local->hour < self::closesAt();
    }

    /** Plain-English description for banners and refusals. */
    public static function describe(): string
    {
        $fmt = fn (int $hour) => Carbon::createFromTime($hour)->format('g:i A');
        $closesMinusOne = Carbon::createFromTime(self::closesAt())->subMinute()->format('g:i A');

        return sprintf(
            'weekdays %s to %s, and all day on weekends',
            $fmt(self::opensAt()),
            $closesMinusOne
        );
    }
}
