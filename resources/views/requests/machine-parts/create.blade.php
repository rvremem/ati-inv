@extends('layouts.app')

@section('title', 'Request Machine Parts')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Request Machine Parts</h2>

    <x-alert />

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Available Machines</h5>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width:150px;">Serial Number</th>
                        <th>Description</th>
                        <th style="width:120px;">Model</th>
                        <th style="width:120px;">Category</th>
                        <th style="width:150px;">Customer</th>
                        <th style="width:120px;">Location</th>
                        <th style="width:120px;">Status</th>
                        <th style="width:150px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($machines as $machine)
                        <tr>
                            <td><strong>{{ $machine->serial_no }}</strong></td>
                            <td>{{ $machine->description }}</td>
                            <td>{{ $machine->model ?? '-' }}</td>
                            <td>{{ $machine->category->code ?? '-' }}</td>
                            <td>{{ $machine->customer->name ?? '-' }}</td>
                            <td>{{ $machine->location->code ?? '-' }}</td>
                            <td><span class="badge bg-success">Available</span></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm request-parts-btn"
                                    data-machine="{{ $machine->toJson() }}">
                                    <i class="fas fa-tools"></i> Request Parts
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Request Parts Modal -->
<div class="modal fade" id="requestPartsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Parts for Machine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="requestPartsForm" method="POST" action="{{ route('requests.machine-parts-store') }}">
                @csrf
                <input type="hidden" name="machine_id" id="modalMachineId">
                <input type="hidden" name="customer_id" id="modalCustomerId">
                <div class="modal-body">
                    <!-- Machine Info -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Machine Serial</label>
                            <input type="text" class="form-control" id="modalSerial" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Description</label>
                            <input type="text" class="form-control" id="modalDescription" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Customer</label>
                            <input type="text" class="form-control" id="modalCustomer" disabled>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Location</label>
                            <input type="text" class="form-control" id="modalLocation" disabled>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Pick-Up Date <span class="text-danger">*</span></label>
                            <input type="date" name="pick_date" id="modalPickDate" class="form-control" required>
                        </div>
                    </div>

                    <hr>

                    <!-- Parts Table -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Parts to Request</strong>
                        <button type="button" id="addPartRowBtn" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Add Part
                        </button>
                    </div>

                    <table class="table table-bordered no-datatable" id="partsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Part Number</th>
                                <th>Description</th>
                                <th style="width:150px;">Serial</th>
                                <th style="width:80px;">Quantity</th>
                                <th>Remarks</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="partRows"></tbody>
                    </table>

                    <div id="noPartsMsg" class="text-center text-muted py-2">
                        Click <strong>Add Part</strong> to add parts to this request.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="modalSubmitBtn" class="btn btn-primary" disabled>
                        <i class="fas fa-save"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const itemsData = @json($items);
let partRowCount = 0;

document.querySelectorAll('.request-parts-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const machine = JSON.parse(this.getAttribute('data-machine'));

        document.getElementById('modalMachineId').value = machine.id;
        document.getElementById('modalCustomerId').value = machine.customer_id || '';
        document.getElementById('modalSerial').value = machine.serial_no;
        document.getElementById('modalDescription').value = machine.description;
        document.getElementById('modalCustomer').value = machine.customer?.name || '-';
        document.getElementById('modalLocation').value = machine.location?.code || '-';
        document.getElementById('modalPickDate').value = '';

        // Reset parts table
        document.getElementById('partRows').innerHTML = '';
        partRowCount = 0;
        document.getElementById('modalSubmitBtn').disabled = true;
        document.getElementById('noPartsMsg').style.display = 'block';

        new bootstrap.Modal(document.getElementById('requestPartsModal')).show();
    });
});

document.getElementById('addPartRowBtn').addEventListener('click', function () {
    partRowCount++;
    document.getElementById('noPartsMsg').style.display = 'none';
    document.getElementById('modalSubmitBtn').disabled = false;

    const row = document.createElement('tr');
    row.id = 'partRow' + partRowCount;
    row.innerHTML = `
        <td>
            <select name="item_part_id[]" class="form-select form-select-sm part-select" data-row="${partRowCount}" required>
                <option value="">-- Select Part --</option>
                ${itemsData.map(i => `<option value="${i.id}">${i.part_no}</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="text" name="description[]" class="form-control form-control-sm" data-row="${partRowCount}" disabled>
        </td>
        <td>
            <select name="serial_no[]" class="form-select form-select-sm" data-row="${partRowCount}" disabled>
                <option value="">N/A</option>
            </select>
        </td>
        <td>
            <input type="number" name="quantity[]" class="form-control form-control-sm" min="1" value="1" data-row="${partRowCount}" required>
        </td>
        <td>
            <input type="text" name="item_remarks[]" class="form-control form-control-sm" data-row="${partRowCount}">
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removePartRow(${partRowCount})">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    document.getElementById('partRows').appendChild(row);

    $(row).find('.part-select').wrap('<span class="select2-sm"></span>').select2({
        theme: 'bootstrap-5', width: '100%',
        dropdownParent: $('#requestPartsModal'),
        placeholder: '-- Select Part --'
    });

    $(row).find('.part-select').on('change', handlePartChange);
});

function handlePartChange(e) {
    const rowId = e.target.dataset.row;
    const item = itemsData.find(i => i.id == e.target.value);
    const descInput = document.querySelector(`input[name="description[]"][data-row="${rowId}"]`);
    const serialSelect = document.querySelector(`select[name="serial_no[]"][data-row="${rowId}"]`);

    if (item) {
        descInput.value = item.description;
        if (item.is_serialized) {
            serialSelect.disabled = false;
            fetch(`/api/item/${item.id}/available-serials`)
                .then(r => r.json())
                .then(data => {
                    serialSelect.innerHTML = '<option value="">-- Select Serial --</option>';
                    data.serials.forEach(s => {
                        const o = document.createElement('option');
                        o.value = s; o.textContent = s;
                        serialSelect.appendChild(o);
                    });
                });
        } else {
            serialSelect.disabled = true;
            serialSelect.innerHTML = '<option value="">N/A</option>';
        }
    }
}

function removePartRow(rowId) {
    document.getElementById('partRow' + rowId).remove();
    if (document.getElementById('partRows').children.length === 0) {
        document.getElementById('modalSubmitBtn').disabled = true;
        document.getElementById('noPartsMsg').style.display = 'block';
    }
}

document.getElementById('requestPartsForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!document.getElementById('modalPickDate').value) {
        alert('Please enter a pick-up date');
        return;
    }
    this.submit();
});
</script>
@endsection
