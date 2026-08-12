<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    protected $table = 'machines';
    protected $fillable = [
        'serial_no',
        'description',
        'model',
        'category_id',
        'customer_id',
        'supplier_id',
        'location_id',
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
