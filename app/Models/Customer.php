<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';
    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function itemParts()
    {
        return $this->belongsToMany(ItemPart::class, 'customer_item_parts');
    }

    public function machines()
    {
        return $this->hasMany(Machine::class);
    }

    public function receiving()
    {
        return $this->hasMany(Receiving::class);
    }

    public function partRequests()
    {
        return $this->hasMany(PartRequest::class);
    }

    public function machineRequests()
    {
        return $this->hasMany(MachineRequest::class);
    }
}
