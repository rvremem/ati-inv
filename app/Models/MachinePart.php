<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MachinePart extends Model
{
    protected $table = 'machine_parts';

    /**
     * Limits every query to the signed-in user's hub, via the parent machine.
     *
     * requested_by is not usable here: parts fitted when a machine was built
     * carry no requester, and scoping on it would hide them entirely. The
     * machine is what sits in a branch, so its hub is the part's hub.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('hub', function (Builder $query) {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            $hubId = $user->hubScope();

            if ($hubId !== null) {
                $query->whereIn(
                    $query->getModel()->getTable() . '.machine_id',
                    Machine::withoutGlobalScope('hub')->where('hub_id', $hubId)->select('id')
                );
            }
        });
    }
    protected $fillable = [
        'machine_id', 'item_part_id', 'quantity', 'serial_no', 'location_id', 'purpose',
        // remarks = why the part was fitted; request_remarks = why it is being
        // pulled. Deliberately separate fields.
        'status', 'remarks', 'request_remarks', 'requested_by', 'requested_at',
        'assembled_by', 'assembled_at', 'disassembled_by', 'disassembled_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'assembled_at' => 'datetime',
        'disassembled_at' => 'datetime',
    ];

    /**
     * Status values, which double as the assembly/disassembly state.
     *
     * A machine_parts row is current state, not an event log: the same row is
     * reused when a part is pulled and later refitted, so these describe where
     * the part is now.
     */
    public const STATUS_ASSEMBLED = 'available';   // fitted to the machine
    public const STATUS_PENDING = 'Pending Out';   // pull requested, not yet approved
    public const STATUS_DISASSEMBLED = 'Out';      // pulled off the machine

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function itemPart()
    {
        return $this->belongsTo(ItemPart::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assembledBy()
    {
        return $this->belongsTo(User::class, 'assembled_by');
    }

    public function disassembledBy()
    {
        return $this->belongsTo(User::class, 'disassembled_by');
    }
}
