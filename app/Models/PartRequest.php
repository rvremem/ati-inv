<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\MachinePart;

class PartRequest extends Model
{
    protected $table = 'part_requests';
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
