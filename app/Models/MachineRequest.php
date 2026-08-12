<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineRequest extends Model
{
    protected $table = 'machine_requests';
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
