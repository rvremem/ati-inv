<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MachineRequest extends Model
{
    protected $table = 'machine_requests';

    /**
     * Limits every query to the signed-in user's hub, via the requester.
     *
     * This table has no hub_id, but it does record who raised the request, so
     * the request belongs to that person's hub — the same rule PartRequest
     * uses, kept identical so the two request types cannot disagree.
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
                    $query->getModel()->getTable() . '.requested_by',
                    User::where('hub_id', $hubId)->select('id')
                );
            }
        });
    }
    protected $fillable = [
        'pick_date', 'customer_id', 'machine_id', 'description', 'remarks',
        'status', 'purpose', 'mris_no', 'location_id', 'approved_by',
        'out_date', 'requested_by'
    ];

    protected $casts = ['pick_date' => 'date', 'out_date' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
