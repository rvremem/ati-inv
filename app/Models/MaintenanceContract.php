<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceContract extends Model
{
    protected $table = 'maintenance_contracts';

    /**
     * Deliberately not hub-scoped: contracts are visible from every hub.
     *
     * A contract covers a customer's equipment, which any branch may be asked
     * to service, so restricting the list by branch would hide cover that the
     * engineer standing in front of the machine needs to see. Defective stock
     * is head-office-only; this is not.
     */
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
