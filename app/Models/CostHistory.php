<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostHistory extends Model
{
    protected $table = 'cost_history';
    protected $fillable = [
        'item_part_id',
        'previous_cost',
        'new_cost',
        'updated_by',
    ];

    protected $casts = [
        'previous_cost' => 'decimal:2',
        'new_cost' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function itemPart()
    {
        return $this->belongsTo(ItemPart::class);
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
