<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Receiving extends Model
{
    protected $table = 'receiving';

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
        'date_received',
        'ati_po',
        'customer_id',
        'supplier_id',
        'item_part_id',
        'location_id',
        'hub_id',
        'quantity',
        'serial_no',
        'status',
        'return_remarks',
        'return_date',
        'returned_by',
        'location_updated_at',
        'location_updated_by',
        'created_by',
    ];

    protected $casts = [
        'date_received' => 'date',
        'return_date' => 'datetime',
        'location_updated_at' => 'datetime',
    ];

    public function hub()
    {
        return $this->belongsTo(Hub::class);
    }

    public function itemPart()
    {
        return $this->belongsTo(ItemPart::class);
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
}
