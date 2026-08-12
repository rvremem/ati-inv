<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Receiving;
use App\Models\Machine;
use App\Models\ItemPart;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Category;
use Illuminate\Http\Request;

class ReceivingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function itemReceiving()
    {
        $items = ItemPart::all();
        $locations = Location::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        return view('receiving.parts.index', compact('items', 'locations', 'suppliers', 'customers'));
    }

    public function itemReceivingCreate()
    {
        $items = ItemPart::all();
        $locations = Location::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        return view('receiving.items.create', compact('items', 'locations', 'suppliers', 'customers'));
    }

    public function itemReceivingStore(Request $request)
    {
        // ati_po and supplier_id were missing here while the form already had an
        // ATI PO field, so every PO number typed on the create screen was
        // dropped — Receiving::create() only ever sees the validated keys.
        // itemReceivingUpdate() validated both already, so edits could set them
        // and creates could not.
        $validated = $request->validate([
            'date_received' => 'required|date',
            'ati_po' => 'nullable',
            'customer_id' => 'required|exists:customers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'item_part_id' => 'required|exists:item_parts,id',
            'location_id' => 'required|exists:locations,id',
            'quantity' => 'required|integer|min:1',
            'serial_no' => 'nullable',
            'status' => 'required|in:Receiving,Return',
            'return_remarks' => 'nullable',
        ]);

        $validated['created_by'] = auth()->id();
        Receiving::create($validated);

        $action = $validated['status'] === 'Return' ? 'Returned Part' : 'Received Part';
        ActivityLog::log($action, "Item Part ID: {$validated['item_part_id']} | Qty: {$validated['quantity']}");

        $message = $validated['status'] === 'Return' ? 'Items returned successfully.' : 'Items received successfully.';
        return redirect()->route('receiving.items')->with('success', $message);
    }

    public function itemReceivingBatchStore(Request $request)
    {
        $validated = $request->validate([
            'date_received' => 'required|date',
            'ati_po' => 'nullable',
            'customer_id' => 'required|exists:customers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'remarks' => 'nullable',
            'item_part_id' => 'required|array',
            'item_part_id.*' => 'exists:item_parts,id',
            'location_id' => 'required|array',
            'location_id.*' => 'exists:locations,id',
            'quantity' => 'required|array',
            'quantity.*' => 'required|integer|min:1',
            'serial_no' => 'nullable|array',
        ]);

        // The PO and supplier are captured once in the header and stamped onto
        // every line of the batch, the same way atiis does it.
        foreach ($validated['item_part_id'] as $index => $itemId) {
            Receiving::create([
                'date_received' => $validated['date_received'],
                'ati_po' => $validated['ati_po'] ?? null,
                'customer_id' => $validated['customer_id'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'item_part_id' => $itemId,
                'location_id' => $validated['location_id'][$index],
                'quantity' => $validated['quantity'][$index],
                'serial_no' => $validated['serial_no'][$index] ?? null,
                'return_remarks' => $validated['remarks'] ?? null,
                'status' => 'Receiving',
                'created_by' => auth()->id(),
            ]);
        }

        ActivityLog::log('Received Parts (Batch)', count($validated['item_part_id']) . ' part(s) received');
        return redirect()->route('receiving.items')->with('success', count($validated['item_part_id']) . ' part(s) received successfully.');
    }

    public function returnPartsStore(Request $request)
    {
        $validated = $request->validate([
            'date_received' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'remarks' => 'nullable',
            'item_part_id' => 'required|array',
            'item_part_id.*' => 'exists:item_parts,id',
            'location_id' => 'required|array',
            'location_id.*' => 'exists:locations,id',
            'quantity' => 'required|array',
            'quantity.*' => 'required|integer|min:1',
            'serial_no' => 'nullable|array',
        ]);

        foreach ($validated['item_part_id'] as $index => $itemId) {
            Receiving::create([
                'date_received' => $validated['date_received'],
                'customer_id' => $validated['customer_id'],
                'item_part_id' => $itemId,
                'location_id' => $validated['location_id'][$index],
                'quantity' => $validated['quantity'][$index],
                'serial_no' => $validated['serial_no'][$index] ?? null,
                'return_remarks' => $validated['remarks'] ?? null,
                'status' => 'Return',
                'created_by' => auth()->id(),
            ]);
        }

        ActivityLog::log('Returned Parts', count($validated['item_part_id']) . ' part(s) returned');
        return redirect()->route('receiving.return-parts')->with('success', count($validated['item_part_id']) . ' part(s) returned successfully.');
    }

    public function itemReceivingEdit(Receiving $receiving)
    {
        $items = ItemPart::all();
        $locations = Location::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        return view('receiving.items.edit', compact('receiving', 'items', 'locations', 'suppliers', 'customers'));
    }

    public function itemReceivingUpdate(Request $request, Receiving $receiving)
    {
        $validated = $request->validate([
            'date_received' => 'required|date',
            'ati_po' => 'nullable',
            'item_part_id' => 'required|exists:item_parts,id',
            'location_id' => 'required|exists:locations,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'customer_id' => 'nullable|exists:customers,id',
            'quantity' => 'required|integer|min:1',
            'serial_no' => 'nullable',
            'status' => 'required|in:Receiving,Return',
            'return_remarks' => 'nullable',
        ]);

        $receiving->update($validated);
        return redirect()->route('receiving.items')->with('success', 'Item updated successfully.');
    }

    public function machineReceiving()
    {
        $machines = Machine::get();
        return view('receiving.machines.index', compact('machines'));
    }

    public function machineReceivingCreate()
    {
        $categories = Category::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        $items = ItemPart::where('is_active', true)->get();
        return view('receiving.machines.create', compact('categories', 'locations', 'suppliers', 'customers', 'items'));
    }

    public function machineReceivingStore(Request $request)
    {
        $validated = $request->validate([
            'serial_no' => 'required|unique:machines',
            'description' => 'required',
            'model' => 'nullable',
            'category_id' => 'required|exists:categories,id',
            'customer_id' => 'required|exists:customers,id',
            'location_id' => 'required|exists:locations,id',
            'date_received' => 'required|date',
            'remarks' => 'nullable',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['status'] = 'Receiving';
        $validated['return_remarks'] = $validated['remarks'];
        $machine = Machine::create($validated);
        ActivityLog::log('Received Machine', "Serial: {$machine->serial_no} | {$machine->description}");
        return redirect()->route('receiving.machines')->with('success', 'Machine received successfully.');
    }

    public function machineReceivingEdit(Machine $machine)
    {
        $categories = Category::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        return view('receiving.machines.edit', compact('machine', 'categories', 'locations', 'suppliers', 'customers'));
    }

    public function machineReceivingUpdate(Request $request, Machine $machine)
    {
        $validated = $request->validate([
            'serial_no' => "required|unique:machines,serial_no,{$machine->id}",
            'description' => 'required',
            'model' => 'nullable',
            'category_id' => 'required|exists:categories,id',
            'customer_id' => 'nullable|exists:customers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'location_id' => 'required|exists:locations,id',
            'ati_po' => 'nullable',
            'date_received' => 'required|date',
            'unit_cost' => 'required|numeric',
            'status' => 'required|in:Received,In Stock,Out,Returned,Defective',
        ]);

        $machine->update($validated);
        ActivityLog::log('Updated Machine (Receiving)', "Serial: {$machine->serial_no} | Status: {$validated['status']}");
        return redirect()->route('receiving.machines')->with('success', 'Machine updated successfully.');
    }

    public function returnParts()
    {
        $locations = Location::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        return view('receiving.returns.parts-index', compact('locations', 'customers'));
    }

    public function returnMachines()
    {
        $customers = Customer::where('is_active', true)->get();
        return view('receiving.returns.machines-index', compact('customers'));
    }

    public function returnMachinesStore(Request $request)
    {
        $validated = $request->validate([
            'date_received' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'remarks' => 'nullable',
            'machine_serial' => 'required|array',
            'machine_serial.*' => 'required|exists:machines,serial_no',
        ]);

        foreach ($validated['machine_serial'] as $serial) {
            $machine = Machine::where('serial_no', $serial)->first();
            if ($machine) {
                $machine->update([
                    // 'Return' rather than 'Receiving': both count as on-hand and
                    // available, but this records that it came back from a request.
                    'status' => 'Return',
                    'return_date' => now(),
                    'return_remarks' => $validated['remarks'],
                    'returned_by' => auth()->user()->name,
                ]);
            }
        }

        ActivityLog::log('Returned Machines', count($validated['machine_serial']) . ' machine(s): ' . implode(', ', $validated['machine_serial']));
        return redirect()->route('receiving.return-machines')->with('success', count($validated['machine_serial']) . ' machine(s) returned successfully.');
    }
}
