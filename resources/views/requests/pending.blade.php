@extends('layouts.app')

@section('title', 'Pending Approvals')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Pending Approvals</h2>

    <x-alert />

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Pending Part Requests</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Part Number</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Serial</th>
                            <th>Client</th>
                            <th>Requestor</th>
                            <th>Pick-Up Date</th>
                            <th>Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($partRequests as $req)
                            <tr>
                                <td><strong>{{ $req->itemPart->part_no }}</strong></td>
                                <td>{{ $req->itemPart->description }}</td>
                                <td>{{ $req->quantity }}</td>
                                <td>{{ $req->serial_no ?? '-' }}</td>
                                <td>{{ $req->customer->name }}</td>
                                <td>{{ $req->requestedBy->name ?? '-' }}</td>
                                <td>{{ $req->pick_date->format('Y-m-d') }}</td>
                                <td>{{ $req->remarks ?? '-' }}</td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm approve-btn"
                                        data-bs-toggle="modal" data-bs-target="#approveModal"
                                        data-req="{{ $req->toJson() }}"
                                        data-item="{{ $req->itemPart->toJson() }}">
                                        Approve
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm decline-btn"
                                        data-bs-toggle="modal" data-bs-target="#declineModal"
                                        data-req-id="{{ $req->id }}">
                                        Decline
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Pending Machine Requests</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Machine Serial</th>
                            <th>Description</th>
                            <th>Model</th>
                            <th>Client</th>
                            <th>Requestor</th>
                            <th>Pick-Up Date</th>
                            <th>Purpose</th>
                            <th>Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($machineRequests as $req)
                            <tr>
                                <td><strong>{{ $req->machine->serial_no }}</strong></td>
                                <td>{{ $req->machine->description }}</td>
                                <td>{{ $req->machine->model ?? '-' }}</td>
                                <td>{{ $req->customer->name }}</td>
                                <td>{{ $req->requestedBy->name ?? '-' }}</td>
                                <td>{{ $req->pick_date->format('Y-m-d') }}</td>
                                <td>{{ $req->purpose ?? '-' }}</td>
                                <td>{{ $req->remarks ?? '-' }}</td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm machine-approve-btn"
                                        data-bs-toggle="modal" data-bs-target="#machineApproveModal"
                                        data-req="{{ $req->toJson() }}">
                                        Approve
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm machine-decline-btn"
                                        data-bs-toggle="modal" data-bs-target="#machineDeclineModal"
                                        data-req-id="{{ $req->id }}">
                                        Decline
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Pending Machine Part Requests</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Machine Serial</th>
                            <th>Part Number</th>
                            <th>Description</th>
                            <th>Serial No</th>
                            <th>Quantity</th>
                            <th>Requestor</th>
                            <th>Date Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($machinePartRequests as $mp)
                            <tr>
                                <td><strong>{{ $mp->machine->serial_no ?? '-' }}</strong></td>
                                <td>{{ $mp->itemPart->part_no ?? '-' }}</td>
                                <td>{{ $mp->itemPart->description ?? '-' }}</td>
                                <td>{{ $mp->serial_no ?? '-' }}</td>
                                <td>{{ $mp->quantity }}</td>
                                {{-- machine_parts.requested_by is nullable: rows requested
                                     before that column existed have nobody to show. Same for
                                     requested_at — hence the dash rather than a made-up date. --}}
                                <td>{{ $mp->requestedBy->name ?? '-' }}</td>
                                <td>
                                    @if ($mp->requested_at)
                                        {{-- Date to match the other tables; full timestamp on
                                             hover, which separates same-day requests. --}}
                                        <span title="{{ $mp->requested_at->format('Y-m-d H:i:s') }}">
                                            {{ $mp->requested_at->format('Y-m-d') }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm mp-approve-btn"
                                        data-bs-toggle="modal" data-bs-target="#machinePartApproveModal"
                                        data-id="{{ $mp->id }}"
                                        data-machine="{{ $mp->machine->serial_no ?? '-' }}"
                                        data-part-no="{{ $mp->itemPart->part_no ?? '-' }}"
                                        data-description="{{ $mp->itemPart->description ?? '-' }}"
                                        data-serial="{{ $mp->serial_no ?? '-' }}"
                                        data-quantity="{{ $mp->quantity }}">
                                        Approve
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm mp-decline-btn"
                                        data-bs-toggle="modal" data-bs-target="#machinePartDeclineModal"
                                        data-id="{{ $mp->id }}">
                                        Decline
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MACHINE PART APPROVE MODAL -->
<div class="modal fade" id="machinePartApproveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Request - Out Machine Parts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="machinePartApproveForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label class="form-label">Machine Serial</label>
                            <input type="text" id="mp_machine" class="form-control" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Part Number</label>
                            <input type="text" id="mp_part_no" class="form-control" disabled>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-8">
                            <label class="form-label">Description</label>
                            <input type="text" id="mp_description" class="form-control" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quantity</label>
                            <input type="text" id="mp_quantity" class="form-control" disabled>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Serial No</label>
                        <input type="text" id="mp_serial" class="form-control" disabled>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Confirm Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MACHINE PART DECLINE MODAL -->
<div class="modal fade" id="machinePartDeclineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Decline Machine Part Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="machinePartDeclineForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p>Are you sure you want to decline this machine part request? The part status will be reset to <strong>available</strong>.</p>
                    @include('requests.partials.decline-remarks')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Decline</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- APPROVE MODAL -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Request - Out Item Parts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="approveForm" method="POST">
                @csrf
                <input type="hidden" id="req_id" name="req_id">
                <input type="hidden" id="serial_no_hidden" name="serial_no">
                <input type="hidden" id="location_id_hidden" name="location_id">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Part Number</label>
                        <input type="text" id="part_no" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <input type="text" id="description" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>Quantity</label>
                        <input type="text" id="quantity" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>Client</label>
                        <input type="text" id="client" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>Serial Number</label>
                        <input type="text" id="serial" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>Location</label>
                        <input type="text" id="location" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>MRIS</label>
                        <input type="text" name="mris_no" id="mris" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Remarks</label>
                        <input type="text" id="remarks" class="form-control" disabled>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirmModal">
                        Out Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CONFIRM MODAL -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Out Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve and out this item?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#approveModal">Back</button>
                <button type="button" class="btn btn-primary" id="confirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- DECLINE MODAL -->
<div class="modal fade" id="declineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Decline Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="declineForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p>Are you sure you want to decline this request?</p>
                    @include('requests.partials.decline-remarks')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Decline</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MACHINE APPROVE MODAL -->
<div class="modal fade" id="machineApproveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Machine Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="machineApproveForm" method="POST">
                @csrf
                <input type="hidden" id="machine_req_id" name="machine_req_id">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Machine Serial</label>
                        <input type="text" id="machine_serial" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <input type="text" id="machine_description" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>Model</label>
                        <input type="text" id="machine_model" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>Client</label>
                        <input type="text" id="machine_client" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>Pick-Up Date</label>
                        <input type="text" id="machine_pickup_date" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>Purpose</label>
                        <input type="text" id="machine_purpose" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label>MRIS</label>
                        <input type="text" name="mris_no" id="machine_mris" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Remarks</label>
                        <input type="text" id="machine_remarks" class="form-control" disabled>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#machineConfirmModal">
                        Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MACHINE CONFIRM MODAL -->
<div class="modal fade" id="machineConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Machine Request Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this machine request?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#machineApproveModal">Back</button>
                <button type="button" class="btn btn-primary" id="machineConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- MACHINE DECLINE MODAL -->
<div class="modal fade" id="machineDeclineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Decline Machine Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="machineDeclineForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p>Are you sure you want to decline this machine request?</p>
                    @include('requests.partials.decline-remarks')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Decline</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const locationsData = @json($locations);

document.querySelectorAll('.approve-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const req = JSON.parse(this.dataset.req);
        const item = JSON.parse(this.dataset.item);
        document.querySelector('#approveModal .modal-title').textContent = 'Approve Request - Out Item Parts';

        document.getElementById('req_id').value = req.id;
        document.getElementById('part_no').value = item.part_no;
        document.getElementById('description').value = item.description;
        document.getElementById('quantity').value = req.quantity;
        document.getElementById('client').value = req.customer.name;
        document.getElementById('mris').value = req.mris_no || '';

        // Pre-fill serial — for machine part requests, use the serial from machine_part
        const serial = req.serial_no || req.machine_part?.serial_no || '';
        document.getElementById('serial').value = serial;
        document.getElementById('serial_no_hidden').value = serial;
        document.getElementById('remarks').value = req.remarks || '';

        // Auto-fill location: by serial for serialised items, otherwise by the
        // part's current stock location (non-serialised items have no serial).
        const locationUrl = serial
            ? `/api/serial/${encodeURIComponent(serial)}/location`
            : `/api/item/${item.id}/location`;

        fetch(locationUrl)
            .then(response => response.json())
            .then(data => {
                if (data.location) {
                    document.getElementById('location').value = data.location;
                    document.getElementById('location_id_hidden').value = data.location_id;
                }
            })
            .catch(error => console.error('Error fetching location:', error));

        document.getElementById('approveForm').action = `/requests/parts/${req.id}/approve`;
    });
});

document.querySelectorAll('.decline-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const reqId = this.dataset.reqId;
        document.getElementById('declineForm').action = `/requests/parts/${reqId}/decline`;
        clearDeclineRemarks('declineForm');
    });
});

function fetchSerials(itemId, locationId) {
    fetch(`/api/item/${itemId}/available-serials`)
        .then(response => response.json())
        .then(data => {
            const serialSelect = document.getElementById('serial');
            serialSelect.innerHTML = '<option value="">-- Select Serial --</option>';
            data.serials.forEach(serial => {
                const option = document.createElement('option');
                option.value = serial;
                option.textContent = serial;
                serialSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error fetching serials:', error));
}

document.getElementById('confirmBtn').addEventListener('click', function() {
    document.getElementById('approveForm').submit();
});

// Machine request approve handlers
document.querySelectorAll('.machine-approve-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const req = JSON.parse(this.dataset.req);

        document.getElementById('machine_req_id').value = req.id;
        document.getElementById('machine_serial').value = req.machine.serial_no;
        document.getElementById('machine_description').value = req.machine.description;
        document.getElementById('machine_model').value = req.machine.model || '-';
        document.getElementById('machine_client').value = req.customer.name;
        document.getElementById('machine_pickup_date').value = new Date(req.pick_date).toLocaleDateString();
        document.getElementById('machine_purpose').value = req.purpose || '';
        document.getElementById('machine_mris').value = req.mris_no || '';
        document.getElementById('machine_remarks').value = req.remarks || '';

        document.getElementById('machineApproveForm').action = `/requests/machines/${req.id}/approve`;
    });
});

document.querySelectorAll('.machine-decline-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const reqId = this.dataset.reqId;
        document.getElementById('machineDeclineForm').action = `/requests/machines/${reqId}/decline`;
        clearDeclineRemarks('machineDeclineForm');
    });
});

document.getElementById('machineConfirmBtn').addEventListener('click', function() {
    document.getElementById('machineApproveForm').submit();
});

// Machine Part approve/decline handlers
document.querySelectorAll('.mp-approve-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('mp_machine').value     = this.dataset.machine;
        document.getElementById('mp_part_no').value    = this.dataset.partNo;
        document.getElementById('mp_description').value = this.dataset.description;
        document.getElementById('mp_serial').value     = this.dataset.serial;
        document.getElementById('mp_quantity').value   = this.dataset.quantity;
        document.getElementById('machinePartApproveForm').action = `/requests/machine-parts/${this.dataset.id}/approve`;
    });
});

document.querySelectorAll('.mp-decline-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('machinePartDeclineForm').action = `/requests/machine-parts/${this.dataset.id}/decline`;
        clearDeclineRemarks('machinePartDeclineForm');
    });
});

// Each decline modal is reused for every row, so a reason typed for one
// request (then cancelled) must not carry over to the next.
function clearDeclineRemarks(formId) {
    document.querySelector(`#${formId} [name="decline_remarks"]`).value = '';
}
</script>
@endsection
