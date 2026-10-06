<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DefectiveItem extends Model
{
    protected $table = 'defective_items';

    /**
     * Head-office data: visible to HO and to the administrative roles only.
     *
     * Defective items and maintenance contracts are handled centrally rather
     * than per branch, so there is no per-hub version of this list to show a
     * branch user — they get nothing rather than everyone else's.
     *
     * Skipped with no session so console commands still see the table.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('headOffice', function (Builder $query) {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            if (! $user->atHeadOffice()) {
                $query->whereRaw('1 = 0');
            }
        });
    }
    protected $fillable = [
        'date_received', 'customer_id', 'engineer_id', 'item_part_id',
        'location_id', 'quantity', 'serial_no', 'return_remarks',
        'return_date', 'returned_by'
    ];

    protected $casts = [
        'date_received' => 'date',
        'return_date' => 'datetime',
    ];

    public function itemPart()
    {
        return $this->belongsTo(ItemPart::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function engineer()
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
