<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\MachinePart;

class PartRequest extends Model
{
    protected $table = 'part_requests';

    /**
     * Limits every query to the signed-in user's hub.
     *
     * This table has no hub_id of its own, so a request belongs to the hub of
     * whoever raised it — the same rule Stock Levels and Parts Location use to
     * attribute the Out column, kept identical so the reports and the stock
     * screens cannot disagree about which branch a request came from.
     *
     * Skipped when nobody is authenticated so console commands and migrations
     * still see everything.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('hub', function (Builder $query) {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            $hubId = $user->hubScope();

            if ($hubId !== null) {
                $query->whereIn(
                    'part_requests.requested_by',
                    User::where('hub_id', $hubId)->select('id')
                );
            }
        });
    }
    protected $fillable = [
        'pick_date',
        'customer_id',
        'machine_id',
        'machine_part_id',
        'item_part_id',
        'quantity',
        'serial_no',
        'status',
        'remarks',
        'mris_no',
        'location_id',
        'approved_by',
        'out_date',
        'requested_by',
    ];

    protected $casts = [
        'pick_date' => 'date',
        'out_date' => 'datetime',
    ];

    public function itemPart()
    {
        return $this->belongsTo(ItemPart::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function machinePart()
    {
        return $this->belongsTo(MachinePart::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
