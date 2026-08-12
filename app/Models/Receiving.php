<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receiving extends Model
{
    protected $table = 'receiving';
    protected $fillable = [
        'date_received',
        'ati_po',
        'customer_id',
        'supplier_id',
        'item_part_id',
        'location_id',
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
