<?php

namespace App\Http\Controllers;

use App\Models\DefectiveItem;
use App\Models\ItemPart;
use App\Models\Location;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;

class DefectiveController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $defectiveItems = DefectiveItem::with('itemPart', 'customer', 'location', 'engineer')
            ->get();
        return view('defective.index', compact('defectiveItems'));
    }

    public function create()
    {
        $items = ItemPart::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        $engineers = User::withRole('Engineer')->where('is_active', true)->get();
        return view('defective.create', compact('items', 'customers', 'locations', 'engineers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_received' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'engineer_id' => 'required|exists:users,id',
            'item_part_id' => 'required|exists:item_parts,id',
            'location_id' => 'required|exists:locations,id',
            'quantity' => 'required|integer|min:1',
            'serial_no' => 'nullable',
            'return_remarks' => 'nullable',
        ]);

        $validated['return_date'] = now();
        $validated['returned_by'] = auth()->user()->name;
        DefectiveItem::create($validated);
        return redirect()->route('defective.index')->with('success', 'Defective item recorded successfully.');
    }

    public function edit(DefectiveItem $defectiveItem)
    {
        $items = ItemPart::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        $engineers = User::withRole('Engineer')->where('is_active', true)->get();
        return view('defective.edit', compact('defectiveItem', 'items', 'customers', 'locations', 'engineers'));
    }

    public function update(Request $request, DefectiveItem $defectiveItem)
    {
        $validated = $request->validate([
            'date_received' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'engineer_id' => 'required|exists:users,id',
            'item_part_id' => 'required|exists:item_parts,id',
            'location_id' => 'required|exists:locations,id',
            'quantity' => 'required|integer|min:1',
            'serial_no' => 'nullable',
            'return_remarks' => 'nullable',
        ]);

        $defectiveItem->update($validated);
        return redirect()->route('defective.index')->with('success', 'Defective item updated successfully.');
    }

    public function destroy(DefectiveItem $defectiveItem)
    {
        $defectiveItem->delete();
        return redirect()->route('defective.index')->with('success', 'Defective item deleted successfully.');
    }

    public function report()
    {
        $defectiveItems = DefectiveItem::with('itemPart', 'customer')
            ->orderBy('date_received', 'desc')
            ->get();
        return view('defective.report', compact('defectiveItems'));
    }
}
