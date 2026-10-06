<?php

namespace App\Models;

use Carbon\Carbon;
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
        'supplier_id',
        'warranty',

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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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

    /**
     * The last day a unit of this part is covered, counted from $start.
     *
     * warranty is free text on the form, so it is read rather than trusted:
     * "3 months", "1 yr", "90 days", "2 weeks". A bare number is taken as
     * months, the unit maintenance contracts already use. Null when there is
     * no warranty recorded or the text holds no duration to count.
     */
    public function warrantyEndsOn($start): ?Carbon
    {
        if (! $start || ! preg_match('/(\d+)\s*(days?|weeks?|wks?|months?|mos?|years?|yrs?)?\b/i', (string) $this->warranty, $m)) {
            return null;
        }

        $n = (int) $m[1];
        $end = Carbon::parse($start)->startOfDay();

        // NoOverflow so Jan 31 + 1 month ends Feb 28, not Mar 3.
        return match (strtolower(substr($m[2] ?? 'm', 0, 1))) {
            'd' => $end->addDays($n),
            'w' => $end->addWeeks($n),
            'y' => $end->addYearsNoOverflow($n),
            default => $end->addMonthsNoOverflow($n),
        };
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
