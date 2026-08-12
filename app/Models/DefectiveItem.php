<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefectiveItem extends Model
{
    protected $table = 'defective_items';
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
