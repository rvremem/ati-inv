<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DefectivePart extends Model
{
    use SoftDeletes;

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
