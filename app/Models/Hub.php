<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hub extends Model
{
    /**
     * Singular, unlike every other table here — the table was created as `hub`,
     * so Eloquent's plural guess (`hubs`) has to be overridden.
     */
    protected $table = 'hub';

    protected $fillable = [
        'hub_name',
        'is_head_office',
    ];

    protected $casts = [
        'is_head_office' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function machines()
    {
        return $this->hasMany(Machine::class);
    }

    public function receiving()
    {
        return $this->hasMany(Receiving::class);
    }
}
