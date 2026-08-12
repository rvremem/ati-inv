<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PartRequest;
use App\Models\MachineRequest;
use App\Models\ItemPart;
use App\Models\Machine;
use App\Models\MachinePart;
use App\Models\Location;
use App\Models\Customer;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    /**
     * Machine statuses that mean "physically on hand, so it can be requested".
     * Availability is decided by machines.status alone — the request lifecycle
     * writes Pending / Out / Return back onto the machine, so a stale row in
     * machine_requests can never make an on-hand machine look unavailable.
     */
    public const MACHINE_AVAILABLE_STATUSES = ['Receiving', 'Return'];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function partRequests()
    {
        $items = ItemPart::all();
        $customers = Customer::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        return view('requests.parts.index', compact('items', 'customers', 'locations'));
    }

    public function partRequestCreate()
    {
        $items = ItemPart::all();
        $customers = Customer::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        return view('requests.parts.create', compact('items', 'customers', 'locations'));
    }

    public function partRequestStore(Request $request)
    {
        $validated = $request->validate([
            'pick_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'item_part_id' => 'required|array',
            'item_part_id.*' => 'exists:item_parts,id',
            'quantity' => 'required|array',
            'quantity.*' => 'required|integer|min:1',
            'serial_no' => 'nullable|array',
            'item_remarks' => 'nullable|array',
        ]);

        $items = $validated['item_part_id'];
        $quantities = $validated['quantity'];
        $serials = $validated['serial_no'] ?? [];
        $itemRemarks = $validated['item_remarks'] ?? [];

        foreach ($items as $index => $itemId) {
            PartRequest::create([
                'pick_date' => $validated['pick_date'],
                'customer_id' => $validated['customer_id'],
                'item_part_id' => $itemId,
                'quantity' => $quantities[$index],
                'serial_no' => $serials[$index] ?? null,
                'remarks' => $itemRemarks[$index] ?? null,
                'status' => 'Pending',
                'requested_by' => auth()->id(),
            ]);
        }

        ActivityLog::log(
            'Created Part Request',
            count($items) . ' item(s) | Pick Date: ' . $validated['pick_date']
        );

        return redirect()->route('requests.parts')->with('success', 'Part request created successfully.');
    }

    public function partRequestEdit(PartRequest $partRequest)
    {
        $items = ItemPart::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        return view('requests.parts.edit', compact('partRequest', 'items', 'customers', 'locations'));
    }

    public function partRequestUpdate(Request $request, PartRequest $partRequest)
    {
        $validated = $request->validate([
            'pick_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'item_part_id' => 'required|exists:item_parts,id',
            'quantity' => 'required|integer|min:1',
            'serial_no' => 'nullable',
            'remarks' => 'nullable',
            'mris_no' => 'nullable',
            'location_id' => 'nullable|exists:locations,id',
            'status' => 'required|in:Pending,Approved,Issued,Cancelled',
        ]);

        $partRequest->update($validated);
        return redirect()->route('requests.parts')->with('success', 'Part request updated successfully.');
    }

    public function partRequestApprove(Request $request, PartRequest $partRequest)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'serial_no'   => 'nullable',
            'mris_no'     => 'nullable',
        ]);

        $partRequest->update([
            'status'      => 'Out',
            'out_date'    => now(),
            'approved_by' => auth()->user()->name,
            'location_id' => $validated['location_id'],
            'serial_no'   => $validated['serial_no'],
            'mris_no'     => $validated['mris_no'],
        ]);

        ActivityLog::log(
            'Approved Part Request',
            "Part Request ID: {$partRequest->id} | MRIS No: {$validated['mris_no']}"
        );

        return redirect()->route('requests.pending')->with('success', 'Part request approved and issued.');
    }

    public function machineRequests()
    {
        // Previously this left-joined the newest machine_requests row via a
        // correlated subquery and excluded Pending/Out. That was the only thing
        // preventing a double request, because nothing wrote the lifecycle back
        // to the machine — so a returned machine whose last request still said
        // "Out" stayed permanently unrequestable. Status on the machine now
        // carries the lifecycle, so the join is gone.
        $machines = Machine::whereIn('status', self::MACHINE_AVAILABLE_STATUSES)
            ->with('category', 'customer', 'location')
            ->orderBy('serial_no')
            ->get();

        return view('requests.machines.index', compact('machines'));
    }

    public function machineRequestCreate()
    {
        $machines = Machine::where('status', 'Receiving')
            ->orWhere('status', 'Return')
            ->with('category', 'customer', 'location')
            ->get();
        return view('requests.machines.available', compact('machines'));
    }

    public function machineRequestStore(Request $request)
    {
        $validated = $request->validate([
            'pick_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'machine_id' => 'required|exists:machines,id',
            'description' => 'nullable',
            'remarks' => 'nullable',
            'purpose' => 'nullable',
            'mris_no' => 'nullable',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $validated['status'] = 'Pending';
        $validated['requested_by'] = auth()->id();
        $machineRequest = MachineRequest::create($validated);

        $machine = Machine::find($validated['machine_id']);
        // Mark the machine itself as spoken for, so it drops out of the
        // available list straight away.
        $machine?->update(['status' => 'Pending']);

        ActivityLog::log(
            'Created Machine Request',
            "Machine: {$machine->serial_no} | Pick Date: {$validated['pick_date']}"
        );

        return redirect()->route('requests.machines')->with('success', 'Machine request created successfully.');
    }

    public function machineRequestEdit(MachineRequest $machineRequest)
    {
        $machines = Machine::where('status', 'In Stock')->orWhere('id', $machineRequest->machine_id)->get();
        $customers = Customer::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        return view('requests.machines.edit', compact('machineRequest', 'machines', 'customers', 'locations'));
    }

    public function machineRequestUpdate(Request $request, MachineRequest $machineRequest)
    {
        $validated = $request->validate([
            'pick_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'machine_id' => 'required|exists:machines,id',
            'description' => 'nullable',
            'remarks' => 'nullable',
            'purpose' => 'nullable',
            'mris_no' => 'nullable',
            'location_id' => 'nullable|exists:locations,id',
            'status' => 'required|in:Pending,Approved,Issued,Cancelled',
        ]);

        $machineRequest->update($validated);

        ActivityLog::log(
            'Updated Machine Request',
            "Machine Request ID: {$machineRequest->id} | Status: {$validated['status']}"
        );

        return redirect()->route('requests.machines')->with('success', 'Machine request updated successfully.');
    }

    public function machineRequestApprove(Request $request, MachineRequest $machineRequest)
    {
        $validated = $request->validate([
            'mris_no' => 'nullable',
        ]);

        $machineRequest->update([
            'status' => 'Out',
            'out_date' => now(),
            'approved_by' => auth()->user()->name,
            'mris_no' => $validated['mris_no'],
        ]);

        $machineRequest->load('machine');
        // The machine has physically left — reflect that on the machine itself.
        $machineRequest->machine?->update([
            'status' => 'Out',
            'out_date' => now(),
        ]);

        ActivityLog::log(
            'Approved Machine Request',
            "Machine: {$machineRequest->machine->serial_no} | MRIS No: {$validated['mris_no']}"
        );

        return redirect()->route('requests.pending')->with('success', 'Machine request approved successfully.');
    }

    public function machinePartRequests()
    {
        $machines = Machine::with('customer', 'location', 'category')
            ->whereIn('status', ['Receiving', 'Return'])
            ->get();
        $items = ItemPart::where('is_active', true)->get();
        return view('requests.machine-parts.index', compact('machines', 'items'));
    }

    public function machinePartRequestCreate()
    {
        $machines = Machine::with('customer', 'location')->get();
        $items = ItemPart::where('is_active', true)->get();
        return view('requests.machine-parts.create', compact('machines', 'items'));
    }

    public function machinePartRequestStore(Request $request)
    {
        $validated = $request->validate([
            'machine_id'       => 'required|exists:machines,id',
            'customer_id'      => 'required|exists:customers,id',
            'pick_date'        => 'required|date',
            'machine_part_id'  => 'required|array',
            'machine_part_id.*'=> 'exists:machine_parts,id',
            'quantity'         => 'required|array',
            'quantity.*'       => 'required|integer|min:1',
            'item_remarks'     => 'nullable|array',
        ]);

        foreach ($validated['machine_part_id'] as $machinePartId) {
            // requested_by is what puts this on the submitter's My Requests page.
            MachinePart::where('id', $machinePartId)->update([
                'status' => 'Pending Out',
                'requested_by' => auth()->id(),
            ]);
        }

        return redirect()->route('requests.machine-parts')->with('success', 'Machine part request submitted successfully.');
    }

    public function partRequestDecline(PartRequest $partRequest)
    {
        $partRequest->update(['status' => 'Denied']);

        ActivityLog::log(
            'Declined Part Request',
            "Part Request ID: {$partRequest->id}"
        );

        return redirect()->route('requests.pending')->with('success', 'Part request declined.');
    }

    public function machinePartApprove(MachinePart $machinePart)
    {
        $machinePart->update(['status' => 'Out']);

        ActivityLog::log(
            'Approved Machine Part Request',
            "Machine Part ID: {$machinePart->id}"
        );

        return redirect()->route('requests.pending')->with('success', 'Machine part approved and issued.');
    }

    public function machinePartDecline(MachinePart $machinePart)
    {
        $machinePart->update(['status' => 'available']);

        ActivityLog::log(
            'Declined Machine Part Request',
            "Machine Part ID: {$machinePart->id}"
        );

        return redirect()->route('requests.pending')->with('success', 'Machine part request declined.');
    }

    public function machineRequestDecline(MachineRequest $machineRequest)
    {
        $machineRequest->load('machine');
        $serial = $machineRequest->machine->serial_no ?? $machineRequest->machine_id;

        // Release the machine back to the available pool. Without this it would
        // stay Pending forever once the request row is gone.
        $machineRequest->machine?->update(['status' => 'Receiving']);

        $machineRequest->delete();

        ActivityLog::log(
            'Declined Machine Request',
            "Machine: {$serial}"
        );

        return redirect()->route('requests.pending')->with('success', 'Machine request declined successfully.');
    }

    public function myRequests()
    {
        // Everything the signed-in user has submitted, newest first. Scoped by
        // requested_by, which every request type now stamps at submit time.
        $userId = auth()->id();

        $partRequests = PartRequest::with('itemPart', 'customer')
            ->where('requested_by', $userId)
            ->whereNull('machine_id')
            ->orderByDesc('id')
            ->get();

        $machineRequests = MachineRequest::with('machine', 'customer')
            ->where('requested_by', $userId)
            ->orderByDesc('id')
            ->get();

        // Machine-part requests are a status on machine_parts rather than rows in
        // their own table, so they are read straight off that model.
        $machinePartRequests = MachinePart::with('itemPart', 'machine')
            ->where('requested_by', $userId)
            ->orderByDesc('updated_at')
            ->get();

        return view('requests.my', compact('partRequests', 'machineRequests', 'machinePartRequests'));
    }

    public function pendingApprovals()
    {
        $partRequests = PartRequest::with('itemPart', 'customer', 'location')
            ->where('status', 'Pending')
            ->whereNull('machine_id')
            ->get();
        $machinePartRequests = MachinePart::with(['itemPart', 'machine'])
            ->where('status', 'Pending Out')
            ->get();
        $machineRequests = MachineRequest::with('machine', 'customer', 'location')
            ->where('status', 'Pending')
            ->get();
        $locations = Location::where('is_active', true)->get();
        return view('requests.pending', compact('partRequests', 'machinePartRequests', 'machineRequests', 'locations'));
    }
}
