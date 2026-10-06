<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    protected $table = 'machines';

    /**
     * Limits every query to the signed-in user's hub.
     *
     * A global scope rather than a filter on each controller: this model is
     * queried from lists, reports, the dashboard and lookups by id, and one
     * forgotten call site is a branch leaking to someone who should not see
     * it. Applying it at the model means new code inherits the rule instead
     * of having to remember it.
     *
     * Skipped entirely when nobody is authenticated, so migrations, seeders
     * and console commands still see the whole table. User::hubScope() is
     * what decides: null for administrative roles, otherwise the hub id, and
     * 0 for a restricted account with no hub — which matches nothing.
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
                $query->where($query->getModel()->getTable() . '.hub_id', $hubId);
            }
        });
    }
    protected $fillable = [
        'serial_no',
        'description',
        'model',
        'category_id',
        'customer_id',
        'supplier_id',
        'location_id',
        'hub_id',
        'ati_po',
        'date_received',
        'unit_cost',
        'status',
        'out_date',
        'return_date',
        'return_remarks',
        'returned_by',
        'created_by',
    ];

    protected $casts = [
        'date_received' => 'date',
        'out_date' => 'datetime',
        'return_date' => 'datetime',
        'unit_cost' => 'decimal:2',
    ];

    public function hub()
    {
        return $this->belongsTo(Hub::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function machineParts()
    {
        return $this->hasMany(MachinePart::class);
    }

    public function parts()
    {
        return $this->hasMany(MachinePart::class);
    }

    public function machineRequests()
    {
        return $this->hasMany(MachineRequest::class);
    }
}
