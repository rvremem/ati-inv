<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'suppliers';
    protected $fillable = ['name', 'address', 'contact_no', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function machines()
    {
        return $this->hasMany(Machine::class);
    }

    public function receiving()
    {
        return $this->hasMany(Receiving::class);
    }
}
