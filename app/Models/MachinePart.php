<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachinePart extends Model
{
    protected $table = 'machine_parts';
    protected $fillable = ['machine_id', 'item_part_id', 'quantity', 'serial_no', 'location_id', 'purpose', 'status', 'remarks', 'requested_by'];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function itemPart()
    {
        return $this->belongsTo(ItemPart::class);
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
