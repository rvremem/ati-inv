<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemPart extends Model
{
    use SoftDeletes;

    protected $table = 'item_parts';
    protected $fillable = [
        'part_no',
        'manufacturer',
        'model',
        'description',
        'unit_cost',
        'min_stock',
        'is_serialized',
        'type',
        'remarks',
        'is_active',
        'location_id',
    ];

    protected $casts = [
        'is_serialized' => 'boolean',
        'is_active' => 'boolean',
        'min_stock' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function receiving()
    {
        return $this->hasMany(Receiving::class);
    }

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'customer_item_parts');
    }

    public function machineParts()
    {
        return $this->hasMany(MachinePart::class);
    }

    public function partRequests()
    {
        return $this->hasMany(PartRequest::class);
    }

    public function defectiveItems()
    {
        return $this->hasMany(DefectiveItem::class);
    }

    public function costHistory()
    {
        return $this->hasMany(CostHistory::class);
    }

    public function isCriticalStock()
    {
        $received = $this->receiving()->where('status', 'Received')->sum('quantity');
        return $received < $this->min_stock;
    }

    public function getTotalQuantity()
    {
        return $this->receiving()
            ->where('status', 'Received')
            ->sum('quantity') ?? 0;
    }
}
