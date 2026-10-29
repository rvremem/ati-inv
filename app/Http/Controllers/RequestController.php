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
use App\Models\Role;
use App\Models\User;
use App\Mail\RequestSubmitted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    use \App\Http\Controllers\Concerns\ScopesByHub;

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
        // Only parts held in this user's hub - see ScopesByHub::partsInHubScope().
        $items = $this->partsInHubScope()->get();
        $customers = Customer::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        return view('requests.parts.index', compact('items', 'customers', 'locations'));
    }

    public function partRequestCreate()
    {
        // Only parts held in this user's hub - see ScopesByHub::partsInHubScope().
        $items = $this->partsInHubScope()->get();
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

        // Resolved once for the whole request rather than per line.
        $parts = ItemPart::whereIn('id', $items)->get(['id', 'part_no', 'is_serialized'])->keyBy('id');
        $partNos = $parts->pluck('part_no', 'id');

        // Unserialised parts may not be requested beyond what is available.
        // Summed per part across the form's rows, so splitting a request over
        // two lines cannot get round it. Serialised parts are limited by the
        // serial picker instead — each serial is one unit.
        $wanted = [];
        foreach ($items as $index => $itemId) {
            if (! ($parts[$itemId]->is_serialized ?? false)) {
                $wanted[$itemId] = ($wanted[$itemId] ?? 0) + (int) $quantities[$index];
            }
        }

        $errors = [];
        foreach ($wanted as $itemId => $qty) {
            $available = $this->requestableQuantity((int) $itemId);

            if ($qty > $available) {
                $errors[] = "{$partNos[$itemId]}: requested {$qty}, only {$available} available.";
            }
        }

        // A serial must be one the dropdown would offer now — in stock, in
        // this hub, and not already Pending or Out — and may appear only once.
        // The dropdown is loaded when the row is added, so without this two
        // people who opened the form together could both request one unit,
        // as could two rows of the same form. A blank serial is still allowed:
        // the approver picks it at approval.
        $serialsFor = [];
        $seen = [];
        foreach ($items as $index => $itemId) {
            $serial = trim((string) ($serials[$index] ?? ''));

            if ($serial === '') {
                continue;
            }

            $partNo = $partNos[$itemId] ?? "ID {$itemId}";

            if (isset($seen[mb_strtolower($serial)])) {
                $errors[] = "{$partNo}: serial {$serial} is on more than one row.";
                continue;
            }
            $seen[mb_strtolower($serial)] = true;

            $serialsFor[$itemId] ??= array_map('mb_strtolower', $this->requestableSerials((int) $itemId));

            if (! in_array(mb_strtolower($serial), $serialsFor[$itemId], true)) {
                $errors[] = "{$partNo}: serial {$serial} is not available — already requested, issued, or not in stock.";
            }
        }

        if ($errors) {
            return back()->withInput()->withErrors(['quantity' => $errors]);
        }
        $customerName = Customer::find($validated['customer_id'])?->name ?? '-';
        $emailRows = [];

        foreach ($items as $index => $itemId) {
            // One serial is one unit. The form locks the field at 1, but that
            // is only the browser; enforced here as well.
            $serial = trim((string) ($serials[$index] ?? '')) ?: null;
            if ($serial !== null) {
                $quantities[$index] = 1;
            }

            PartRequest::create([
                'pick_date' => $validated['pick_date'],
                'customer_id' => $validated['customer_id'],
                'item_part_id' => $itemId,
                'quantity' => $quantities[$index],
                'serial_no' => $serial,
                'remarks' => $itemRemarks[$index] ?? null,
                'status' => 'Pending',
                'requested_by' => auth()->id(),
            ]);

            // One entry per item, not one summary: "3 item(s)" named nothing
            // that could be traced back to a part or a serial.
            $summary = sprintf(
                'Item No: %s | %s | Customer: %s',
                $partNos[$itemId] ?? "ID {$itemId}",
                ActivityLog::unitLabel($serial, (int) $quantities[$index]),
                $customerName
            );
            ActivityLog::log('Requested Item', $summary);
            $emailRows[] = [
                $partNos[$itemId] ?? "ID {$itemId}",
                $serial ?? '-',
                $quantities[$index],
                ($itemRemarks[$index] ?? null) ?: '-',
            ];
        }

        $this->notifyApprovers('Part', ['Item No', 'Serial', 'Qty', 'Remarks'], $emailRows, [
            'Customer' => $customerName,
            'Pick Date' => $validated['pick_date'],
        ]);

        return redirect()->route('requests.parts')->with('success', 'Part request created successfully.');
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
            // ?? because validate() omits a nullable key that wasn't submitted.
            'serial_no'   => $validated['serial_no'] ?? null,
            'mris_no'     => $validated['mris_no'] ?? null,
        ]);

        // Same shape as the 'Requested Item' entry so a request and its approval
        // line up when you read the log. Re-read after the update: approving is
        // where the serial is finally chosen for a serialised part.
        $partRequest->refresh()->loadMissing('itemPart', 'customer');

        ActivityLog::log('Approved Item Request', sprintf(
            'Item No: %s | %s | Customer: %s | MRIS: %s',
            $partRequest->itemPart->part_no ?? '-',
            ActivityLog::unitLabel($partRequest->serial_no, (int) $partRequest->quantity),
            $partRequest->customer->name ?? '-',
            $partRequest->mris_no ?: '-'
        ));

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

        // purpose is nullable on the form, hence the fallback.
        $customerName = Customer::find($validated['customer_id'])?->name ?? '-';
        // ?? as well as ?: — validate() omits a nullable key entirely when
        // the field isn't submitted, so reading it directly can throw.
        $purpose = ($validated['purpose'] ?? null) ?: '-';

        ActivityLog::log('Requested Machine', sprintf(
            'Serial: %s | Customer: %s | Purpose: %s',
            $machine->serial_no,
            $customerName,
            $purpose
        ));

        $this->notifyApprovers('Machine', ['Serial', 'Model', 'Purpose', 'Remarks'], [[
            $machine->serial_no,
            $machine->model ?? '-',
            $purpose,
            ($validated['remarks'] ?? null) ?: '-',
        ]], [
            'Customer' => $customerName,
            'Pick Date' => $validated['pick_date'],
        ]);

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
            // ?? because validate() omits a nullable key that wasn't submitted.
            'mris_no' => $validated['mris_no'] ?? null,
        ]);

        $machineRequest->load('machine');
        // The machine has physically left — reflect that on the machine itself.
        $machineRequest->machine?->update([
            'status' => 'Out',
            'out_date' => now(),
        ]);

        // Mirrors the 'Requested Machine' entry.
        $machineRequest->loadMissing('machine', 'customer');

        ActivityLog::log('Approved Machine Request', sprintf(
            'Serial: %s | Customer: %s | Purpose: %s | MRIS: %s',
            $machineRequest->machine->serial_no ?? '-',
            $machineRequest->customer->name ?? '-',
            $machineRequest->purpose ?: '-',
            $machineRequest->mris_no ?: '-'
        ));

        return redirect()->route('requests.pending')->with('success', 'Machine request approved successfully.');
    }

    public function machinePartRequests()
    {
        $machines = Machine::with('customer', 'location', 'category')
            ->whereIn('status', ['Receiving', 'Return'])
            ->get();
        $items = $this->partsInHubScope()->where('is_active', true)->get();
        return view('requests.machine-parts.index', compact('machines', 'items'));
    }

    public function machinePartRequestCreate()
    {
        $machines = Machine::with('customer', 'location')->get();
        $items = $this->partsInHubScope()->where('is_active', true)->get();
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

        // Loaded once, before the update, so the log can name the part and the
        // machine rather than a machine_parts id.
        $parts = MachinePart::with('itemPart', 'machine')
            ->whereIn('id', $validated['machine_part_id'])
            ->get()
            ->keyBy('id');
        $emailRows = [];

        foreach ($validated['machine_part_id'] as $index => $machinePartId) {
            $requestRemark = trim((string) ($validated['item_remarks'][$index] ?? '')) ?: null;

            // requested_by is what puts this on the submitter's My Requests page.
            // requested_at is stamped here because the request is only a status
            // flip on an existing row — nothing else records when it happened.
            // request_remarks is stored rather than dropped, so the approval can
            // report the same reason the requester gave.
            MachinePart::where('id', $machinePartId)->update([
                'status' => 'Pending Out',
                'requested_by' => auth()->id(),
                'requested_at' => now(),
                'request_remarks' => $requestRemark,
            ]);

            // Pulling a part off a machine left no trace at all before this.
            //
            // The last field is the requester's own remarks, not machine_parts.
            // purpose: the request form has no purpose input, and the purpose on
            // the row describes why the part was *fitted*, which is a different
            // question from why it is being pulled.
            $part = $parts->get($machinePartId);

            $summary = sprintf(
                'Item No: %s | %s | Machine: %s | Remarks: %s',
                $part?->itemPart?->part_no ?? "ID {$machinePartId}",
                ActivityLog::unitLabel($part?->serial_no, (int) ($validated['quantity'][$index] ?? 1)),
                $part?->machine?->serial_no ?? '-',
                $requestRemark ?: '-'
            );
            ActivityLog::log('Requested Machine Part', $summary);
            $emailRows[] = [
                $part?->itemPart?->part_no ?? "ID {$machinePartId}",
                $part?->serial_no ?: '-',
                $validated['quantity'][$index] ?? 1,
                $part?->machine?->serial_no ?? '-',
                $requestRemark ?: '-',
            ];
        }

        $this->notifyApprovers('Machine Part', ['Item No', 'Serial', 'Qty', 'Machine', 'Remarks'], $emailRows, [
            'Customer' => Customer::find($validated['customer_id'])?->name ?? '-',
            'Pick Date' => $validated['pick_date'],
        ]);

        return redirect()->route('requests.machine-parts')->with('success', 'Machine part request submitted successfully.');
    }

    public function partRequestDecline(PartRequest $partRequest)
    {
        $partRequest->update(['status' => 'Denied']);

        // Same shape as the request and approval entries, so all three stages of
        // one request read alike. No MRIS: nothing was issued.
        $partRequest->loadMissing('itemPart', 'customer');

        ActivityLog::log('Declined Item Request', sprintf(
            'Item No: %s | %s | Customer: %s',
            $partRequest->itemPart->part_no ?? '-',
            ActivityLog::unitLabel($partRequest->serial_no, (int) $partRequest->quantity),
            $partRequest->customer->name ?? '-'
        ));

        return redirect()->route('requests.pending')->with('success', 'Part request declined.');
    }

    public function machinePartApprove(MachinePart $machinePart)
    {
        // Approving is the moment the part actually comes off the machine, so
        // this is where the disassembly is recorded — the approver is the one
        // who released it, which is not necessarily the requester.
        $machinePart->update([
            'status' => MachinePart::STATUS_DISASSEMBLED,
            'disassembled_by' => auth()->id(),
            'disassembled_at' => now(),
        ]);

        // Mirrors the 'Requested Machine Part' entry, down to the same remarks:
        // request_remarks is what the requester typed, so the pair now reads
        // consistently. No MRIS here — machine-part requests never carry one.
        $machinePart->loadMissing('itemPart', 'machine');

        ActivityLog::log('Approved Machine Part Request', sprintf(
            'Item No: %s | %s | Machine: %s | Remarks: %s',
            $machinePart->itemPart->part_no ?? '-',
            ActivityLog::unitLabel($machinePart->serial_no, (int) $machinePart->quantity),
            $machinePart->machine->serial_no ?? '-',
            $machinePart->request_remarks ?: '-'
        ));

        return redirect()->route('requests.pending')->with('success', 'Machine part approved and issued.');
    }

    public function machinePartDecline(MachinePart $machinePart)
    {
        // Declining leaves the part on the machine, so nothing is disassembled
        // and the disassembly columns stay as they were.
        $machinePart->update(['status' => MachinePart::STATUS_ASSEMBLED]);

        // request_remarks is left in place by the decline, so the entry can
        // still say what was asked for and refused.
        $machinePart->loadMissing('itemPart', 'machine');

        ActivityLog::log('Declined Machine Part Request', sprintf(
            'Item No: %s | %s | Machine: %s | Remarks: %s',
            $machinePart->itemPart->part_no ?? '-',
            ActivityLog::unitLabel($machinePart->serial_no, (int) $machinePart->quantity),
            $machinePart->machine->serial_no ?? '-',
            $machinePart->request_remarks ?: '-'
        ));

        return redirect()->route('requests.pending')->with('success', 'Machine part request declined.');
    }

    public function machineRequestDecline(MachineRequest $machineRequest)
    {
        $machineRequest->load('machine', 'customer');

        $serial = $machineRequest->machine->serial_no ?? $machineRequest->machine_id;
        $customerName = $machineRequest->customer->name ?? '-';
        $purpose = $machineRequest->purpose ?: '-';

        // Release the machine back to the available pool, otherwise it would
        // stay Pending forever.
        $machineRequest->machine?->update(['status' => 'Receiving']);

        // Marked Denied, not deleted. This used to hard-delete the row —
        // machine_requests has no soft deletes — so a refused machine request
        // vanished completely and could not be audited afterwards. Part
        // requests already worked this way; this brings the two in line.
        $machineRequest->update(['status' => 'Denied']);

        ActivityLog::log('Declined Machine Request', sprintf(
            'Serial: %s | Customer: %s | Purpose: %s',
            $serial,
            $customerName,
            $purpose
        ));

        return redirect()->route('requests.pending')->with('success', 'Machine request declined successfully.');
    }

    /**
     * Send one email that a request is waiting: Admins and SAs (the approvers)
     * as To, Developers and the requester as CC. If there is no approver yet,
     * the Developers move up to To so the email still has a main recipient.
     *
     * The request is already saved by now, so a mail failure (SMTP down, bad
     * credentials) is logged rather than thrown — it must not turn a
     * successful submission into an error page.
     */
    private function notifyApprovers(string $type, array $headers, array $rows, array $details = []): void
    {
        if (! $rows) {
            return;
        }

        $emailsFor = fn (array $codes) => User::whereIn('role_id', array_filter(array_map([Role::class, 'idForCode'], $codes)))
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->values();

        $to = $emailsFor(['Admin', 'SA']);
        $cc = $emailsFor(['Developer']);

        if ($to->isEmpty()) {
            [$to, $cc] = [$cc, collect()];
        }

        if ($to->isEmpty()) {
            return;
        }

        // The requester gets a copy as their record of what was submitted.
        if ($requesterEmail = auth()->user()->email) {
            $cc->push($requesterEmail);
        }

        // No address twice — e.g. an Admin raising their own request is
        // already in To.
        $cc = $cc->unique()->diff($to)->values();

        try {
            Mail::to($to->all())
                ->cc($cc->all())
                ->send(new RequestSubmitted($type, auth()->user(), $headers, $rows, $details));
        } catch (\Throwable $e) {
            Log::error("Request notification email failed: {$e->getMessage()}");
        }
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
        // requestedBy is eager-loaded for the Requestor column — without it the
        // page runs one extra query per pending row.
        $partRequests = PartRequest::with('itemPart', 'customer', 'location', 'requestedBy')
            ->where('status', 'Pending')
            ->whereNull('machine_id')
            ->get();
        $machinePartRequests = MachinePart::with(['itemPart', 'machine', 'requestedBy'])
            ->where('status', 'Pending Out')
            ->get();
        $machineRequests = MachineRequest::with('machine', 'customer', 'location', 'requestedBy')
            ->where('status', 'Pending')
            ->get();
        $locations = Location::where('is_active', true)->get();
        return view('requests.pending', compact('partRequests', 'machinePartRequests', 'machineRequests', 'locations'));
    }
}
