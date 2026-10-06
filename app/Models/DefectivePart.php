<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DefectivePart extends Model
{
    use SoftDeletes;

    /**
     * Head-office data: visible to HO and to the administrative roles only.
     *
     * Defective stock is handled centrally rather than per branch, so there is
     * no per-hub version of this list to show a branch user — they get nothing
     * rather than everyone else's.
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
        'item_part_id',
        'serial_no',
        'quantity',
        'reason',
        'location_id',
        'status',
        'created_by',
        'remarks',
    ];

    public function itemPart()
    {
        return $this->belongsTo(ItemPart::class);
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
