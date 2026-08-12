<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'locations';
    protected $fillable = [
        'code',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
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
}
