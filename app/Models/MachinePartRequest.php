<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachinePartRequest extends Model
{
    protected $fillable = [
        'machine_part_id', 'customer_id', 'requested_by',
        'pick_date', 'quantity', 'remarks', 'status',
    ];

    protected $casts = ['pick_date' => 'date'];

    public function machinePart()   { return $this->belongsTo(MachinePart::class); }
    public function customer()      { return $this->belongsTo(Customer::class); }
    public function requestedBy()   { return $this->belongsTo(User::class, 'requested_by'); }
}
