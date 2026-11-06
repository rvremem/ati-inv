<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class MaintenanceContract extends Model
{
    protected $table = 'maintenance_contracts';

    /**
     * Deliberately not hub-scoped: contracts are visible from every hub.
     *
     * A contract covers a customer's equipment, which any branch may be asked
     * to service, so restricting the list by branch would hide cover that the
     * engineer standing in front of the machine needs to see. Defective stock
     * is head-office-only; this is not.
     *
     * 'customer' is the client's name as written, not a link to the Customers
     * table — see the 2026_10_29_000002 migration.
     */
    protected $fillable = [
        'account_manager', 'equipment_type', 'customer', 'po_no', 'sites',
        'brand', 'description', 'location', 'company', 'branch', 'serial_no',
        'coverage_months', 'start_date', 'end_date', 'remarks', 'is_active',
        'auto_renew',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_renew' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * The term an auto-renewing contract is in today: [start, end].
     *
     * Each term starts on the previous one's end date and runs for $months.
     * A contract several terms behind (uploaded from an old spreadsheet, or
     * not looked at for a while) is rolled forward as many times as it takes
     * for the end date to be after today. NoOverflow keeps a term starting on
     * the 31st ending on the last day of a short month, not spilling into the
     * next one.
     */
    public static function currentTerm(Carbon $start, Carbon $end, int $months): array
    {
        $today = Carbon::today();
        $start = $start->copy();
        $end = $end->copy();

        while ($months > 0 && $end->lte($today)) {
            $start = $end->copy();
            $end = $start->copy()->addMonthsNoOverflow($months);
        }

        return [$start, $end];
    }

    /**
     * Rolls every active auto-renew contract that has reached its end date
     * into its current term. Returns the contracts renewed.
     *
     * Run once a day by the RenewMaintenanceContracts middleware and by the
     * contracts:auto-renew command, whichever gets there first.
     */
    public static function renewExpired(): array
    {
        $renewed = [];

        static::where('auto_renew', true)
            ->where('is_active', true)
            ->where('coverage_months', '>', 0)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', Carbon::today())
            ->get()
            ->each(function (self $contract) use (&$renewed) {
                [$start, $end] = static::currentTerm($contract->end_date, $contract->end_date, $contract->coverage_months);

                // currentTerm() was handed end..end, so the first pass makes
                // the old end date the new start, as a renewal should.
                $contract->update(['start_date' => $start, 'end_date' => $end]);
                $renewed[] = $contract;
            });

        if ($renewed !== []) {
            ActivityLog::create([
                'user_id' => null,
                'user_role' => 'System',
                'action' => 'Auto-Renewed Maintenance Contracts',
                'remarks' => \Illuminate\Support\Str::limit(
                    count($renewed) . ' renewed | ' . collect($renewed)
                        ->map(fn ($c) => "{$c->po_no}/{$c->serial_no} to {$c->end_date->format('Y-m-d')}")
                        ->implode(', '),
                    252
                ),
            ]);
        }

        return $renewed;
    }
}
