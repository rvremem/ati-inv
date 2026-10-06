<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Receiving;
use App\Models\Machine;
use App\Models\ItemPart;
use App\Models\Hub;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReceivingController extends Controller
{
    use \App\Http\Controllers\Concerns\ScopesByHub;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * The activity-log remark for one received or returned part line.
     *
     * The serial-or-quantity rule lives on ActivityLog::unitLabel(), shared
     * with the request logs so the two cannot drift apart.
     *
     * Callers pass resolved labels rather than ids — the batch import looks
     * them up once for the whole file instead of per row.
     */
    private function receivingRemark(
        string $partNo,
        ?string $serial,
        int $quantity,
        string $locationCode,
        $date
    ): string {
        return sprintf(
            'Item No: %s | %s | Location: %s | Date: %s',
            $partNo,
            ActivityLog::unitLabel($serial, $quantity),
            $locationCode,
            $this->logDate($date)
        );
    }

    /**
     * A serialised part is always one physical unit, so its quantity is 1.
     *
     * Enforced here rather than trusted from the form: the receiving row is what
     * Stock Levels and Parts Location count, and a serialised row claiming a
     * quantity of 5 would inflate stock against a single serial. The forms set
     * and lock the field too — this is the guarantee behind them.
     *
     * @param  array<int|string>  $itemPartIds
     * @return \Illuminate\Support\Collection<int, bool> keyed by item_part_id
     */
    private function serialisedFlags(array $itemPartIds)
    {
        return ItemPart::whereIn('id', $itemPartIds)->pluck('is_serialized', 'id');
    }

    /** Dates in remarks follow the same "M d, Y" the reports display. */
    private function logDate($date): string
    {
        try {
            return Carbon::parse($date)->format('M d, Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }

    public function itemReceiving()
    {
        $items = ItemPart::all();
        $locations = Location::where('is_active', true)->get();
        // Plus any supplier a part still points at, retired or not: the form
        // locks Supplier to the part's own, and one missing from the list
        // would lock it to a blank.
        $suppliers = Supplier::where('is_active', true)
            ->orWhereIn('id', ItemPart::whereNotNull('supplier_id')->select('supplier_id'))
            ->get();
        $customers = Customer::where('is_active', true)->get();
        $hubs = $this->selectableHubs();
        return view('receiving.parts.index', compact('items', 'locations', 'suppliers', 'customers', 'hubs'));
    }

    public function itemReceivingCreate()
    {
        $items = ItemPart::all();
        $locations = Location::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        $hubs = $this->selectableHubs();
        return view('receiving.items.create', compact('items', 'locations', 'suppliers', 'customers', 'hubs'));
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
        'status' => 'required|in:Receiving,Old Stock',
        'hub_id' => 'nullable|exists:hub,id',
        'return_remarks' => 'nullable',
    ]);

    // A restricted user may only file against their own hub. The form
    // offers only that one, but hub_id is a posted value — without this,
    // editing the request would book stock into a branch they cannot see.
    if (($allowedHub = $this->hubScope()) !== null) {
        $validated['hub_id'] = $allowedHub;
    }

    $validated['created_by'] = auth()->id();

    // One serial, one unit — see serialisedFlags().
    if ($this->serialisedFlags([$validated['item_part_id']])->get($validated['item_part_id'])) {
        $validated['quantity'] = 1;
    }

    Receiving::create($validated);

    // Was "Item Part ID: 7 | Qty: 2" — a database id nobody can trace and no
    // serial, location or date.
    ActivityLog::log('Received Item', $this->receivingRemark(
        ItemPart::find($validated['item_part_id'])?->part_no ?? "ID {$validated['item_part_id']}",
        $validated['serial_no'] ?? null,
        (int) $validated['quantity'],
        Location::find($validated['location_id'])?->code ?? '-',
        $validated['date_received']
    ));

    return redirect()->route('receiving.items')->with('success', 'Items received successfully.');
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
            'hub_id' => 'nullable|exists:hub,id',
            'status' => 'required|in:Receiving,Old Stock',
            'quantity' => 'required|array',
            'quantity.*' => 'required|integer|min:1',
            'serial_no' => 'nullable|array',
        ]);

        // A restricted user may only file against their own hub. The form
        // offers only that one, but hub_id is a posted value — without this,
        // editing the request would book stock into a branch they cannot see.
        if (($allowedHub = $this->hubScope()) !== null) {
            $validated['hub_id'] = $allowedHub;
        }

        // Supplier follows the parts. The form locks it to their supplier and
        // refuses a mixed batch; this is the guarantee behind that, since the
        // posted value can be edited. The picked supplier is used only when
        // none of the parts has one on file.
        $partSuppliers = ItemPart::whereIn('id', $validated['item_part_id'])
            ->whereNotNull('supplier_id')
            ->distinct()
            ->pluck('supplier_id');

        if ($partSuppliers->count() > 1) {
            return back()->withInput()->withErrors([
                'supplier_id' => 'These parts come from different suppliers. Receive each supplier\'s parts as a separate batch.',
            ]);
        }

        if ($partSuppliers->isNotEmpty()) {
            $validated['supplier_id'] = $partSuppliers->first();
        }

        // Looked up once for the whole batch rather than per line.
        $partNos = ItemPart::whereIn('id', $validated['item_part_id'])->pluck('part_no', 'id');
        $locationCodes = Location::whereIn('id', $validated['location_id'])->pluck('code', 'id');
        $serialised = $this->serialisedFlags($validated['item_part_id']);

        // The PO and supplier are captured once in the header and stamped onto
        // every line of the batch, the same way atiis does it.
        foreach ($validated['item_part_id'] as $index => $itemId) {
            // One serial, one unit — see serialisedFlags().
            $quantity = $serialised->get($itemId) ? 1 : (int) $validated['quantity'][$index];

            Receiving::create([
                'date_received' => $validated['date_received'],
                'ati_po' => $validated['ati_po'] ?? null,
                'customer_id' => $validated['customer_id'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'item_part_id' => $itemId,
                'location_id' => $validated['location_id'][$index],
                'hub_id' => $validated['hub_id'] ?? null,
                'quantity' => $quantity,
                'serial_no' => $validated['serial_no'][$index] ?? null,
                'return_remarks' => $validated['remarks'] ?? null,
                'status' => $validated['status'],   // was hardcoded 'Receiving'
                'created_by' => auth()->id(),
            ]);

            // One entry per line, not one summary for the batch: "3 part(s)
            // received" cannot be traced back to a part number or a serial,
            // which is the whole point of logging it.
            ActivityLog::log('Received Item', $this->receivingRemark(
                $partNos[$itemId] ?? "ID {$itemId}",
                $validated['serial_no'][$index] ?? null,
                $quantity,
                $locationCodes[$validated['location_id'][$index]] ?? '-',
                $validated['date_received']
            ));
        }
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
            'hub_id' => 'nullable|exists:hub,id',
            'quantity' => 'required|array',
            'quantity.*' => 'required|integer|min:1',
            'serial_no' => 'nullable|array',
        ]);

        // A restricted user may only file against their own hub. The form
        // offers only that one, but hub_id is a posted value — without this,
        // editing the request would book stock into a branch they cannot see.
        if (($allowedHub = $this->hubScope()) !== null) {
            $validated['hub_id'] = $allowedHub;
        }

        $serialised = $this->serialisedFlags($validated['item_part_id']);

        foreach ($validated['item_part_id'] as $index => $itemId) {
            // One serial, one unit — see serialisedFlags().
            $quantity = $serialised->get($itemId) ? 1 : (int) $validated['quantity'][$index];

            Receiving::create([
                'date_received' => $validated['date_received'],
                'customer_id' => $validated['customer_id'],
                'item_part_id' => $itemId,
                'location_id' => $validated['location_id'][$index],
                'hub_id' => $validated['hub_id'] ?? null,
                'quantity' => $quantity,
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
        // 'remarks' is nullable, so validate() leaves the key out entirely when
        // the field isn't submitted — reading it directly threw there.
        $validated['return_remarks'] = $validated['remarks'] ?? null;
        $machine = Machine::create($validated);

        // Machine counterpart of 'Received Item'. No quantity branch: a machine
        // is always a single serialised unit.
        ActivityLog::log('Received Machine', sprintf(
            'Serial: %s | Location: %s | Date: %s',
            $machine->serial_no,
            Location::find($validated['location_id'])?->code ?? '-',
            $this->logDate($validated['date_received'])
        ));
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
        $hubs = $this->selectableHubs();
        return view('receiving.returns.parts-index', compact('locations', 'customers', 'hubs'));
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
