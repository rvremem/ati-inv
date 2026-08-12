<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceContract extends Model
{
    protected $table = 'maintenance_contracts';
    protected $fillable = [
        'account_manager', 'equipment_type', 'customer_id', 'po_no', 'sites',
        'brand', 'description', 'location', 'company', 'branch', 'serial_no',
        'coverage_months', 'start_date', 'end_date', 'remarks', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
