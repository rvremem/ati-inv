<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DefectivePart;
use App\Models\ItemPart;
use App\Models\Location;
use Illuminate\Http\Request;

class DefectivePartController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $defectiveParts = DefectivePart::with('itemPart', 'location', 'createdBy')->get();
        return view('defective-parts.index', compact('defectiveParts'));
    }

    public function create()
    {
        $items = ItemPart::all();
        $locations = Location::where('is_active', true)->get();
        return view('defective-parts.create', compact('items', 'locations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_part_id' => 'required|exists:item_parts,id',
            'serial_no' => 'nullable|string',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string',
            'location_id' => 'required|exists:locations,id',
            'remarks' => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['status'] = 'Defective';

        $defectivePart = DefectivePart::create($validated);
        ActivityLog::log('Recorded Defective Part', $this->describe($defectivePart));

        return redirect()->route('defective-parts.index')->with('success', 'Defective part recorded successfully.');
    }

    public function edit(DefectivePart $defectivePart)
    {
        $items = ItemPart::all();
        $locations = Location::where('is_active', true)->get();
        return view('defective-parts.edit', compact('defectivePart', 'items', 'locations'));
    }

    public function update(Request $request, DefectivePart $defectivePart)
    {
        $validated = $request->validate([
            'item_part_id' => 'required|exists:item_parts,id',
            'serial_no' => 'nullable|string',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string',
            'location_id' => 'required|exists:locations,id',
            'remarks' => 'nullable|string',
        ]);

        $defectivePart->update($validated);
        ActivityLog::log('Updated Defective Part', $this->describe($defectivePart));

        return redirect()->route('defective-parts.index')->with('success', 'Defective part updated successfully.');
    }

    /**
     * Soft-deletes the record (DefectivePart uses SoftDeletes), so this is
     * recoverable. Kept deliberately: atiis lacks this action only because the
     * whole module is disabled against the legacy database, not by design.
     */
    public function destroy(DefectivePart $defectivePart)
    {
        // Describe before deleting - the relations are needed for the log line.
        $description = $this->describe($defectivePart);

        $defectivePart->delete();
        ActivityLog::log('Deleted Defective Part', $description);

        return redirect()->route('defective-parts.index')->with('success', 'Defective part deleted successfully.');
    }

    private function describe(DefectivePart $defectivePart): string
    {
        $partNo = $defectivePart->itemPart->part_no ?? "item #{$defectivePart->item_part_id}";
        $serial = $defectivePart->serial_no ? " | Serial: {$defectivePart->serial_no}" : '';

        return "ID: {$defectivePart->id} | Part No: {$partNo}{$serial} | Qty: {$defectivePart->quantity}";
    }
}
