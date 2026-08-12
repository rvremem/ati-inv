@extends('layouts.app')

@section('title', 'Return Machines')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Return Machines</h2>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <form id="returnForm" method="POST" action="{{ route('receiving.return-machines-store') }}">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-6">
                        <x-form-group
                            label="Date Received"
                            name="date_received"
                            type="date"
                            required
                        />
                    </div>
                    <div class="col-md-6">
                        <x-form-group
                            label="Client"
                            name="customer_id"
                            type="select"
                            required
                        >
                            <option value="">-- Select Client --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <x-form-group
                            label="Remarks"
                            name="remarks"
                            type="text"
                        />
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered no-datatable" id="returnTable">
                        <thead class="table-light">
                            <tr>
                                <th>Machine Serial</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th style="width: 50px;">
                                    <button type="button" id="addRowBtn" class="btn btn-success btn-sm">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="returnRows">
                        </tbody>
                    </table>
                </div>

                <div class="text-center">
                    <a href="{{ route('receiving.return-machines') }}" class="btn btn-secondary me-2">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" id="submitBtn" class="btn btn-primary" hidden>
                        <i class="fas fa-save"></i> Return Machine/s
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let rowCount = 0;
let allMachines = [];

// Fetch all machines with status 'Out' on page load
fetch('/api/returnable-machines')
    .then(response => {
        if (!response.ok) throw new Error('API error: ' + response.status);
        return response.json();
    })
    .then(data => {
        allMachines = data.machines || [];
        console.log('Loaded ' + allMachines.length + ' machines');
        document.getElementById('addRowBtn').disabled = false;
    })
    .catch(error => {
        console.error('Error fetching machines:', error);
        document.getElementById('addRowBtn').disabled = true;
    });

document.getElementById('addRowBtn').addEventListener('click', function() {
    if (allMachines.length === 0) {
        alert('No returnable machines available');
        return;
    }
    addRow();
    document.getElementById('submitBtn').hidden = false;
});

function addRow() {
    rowCount++;
    console.log('Adding row ' + rowCount + ' with ' + allMachines.length + ' machines');
    const row = document.createElement('tr');
    row.id = 'row' + rowCount;

    const machineOptions = allMachines.map(m => `<option value="${m.serial_no}" data-machine='${JSON.stringify(m)}'>${m.serial_no}</option>`).join('');
    console.log('Machine options:', machineOptions);

    row.innerHTML = `
        <td>
            <select name="machine_serial[]" class="form-select form-select-sm machine-select" data-row="${rowCount}" required>
                <option value="">-- Select Machine --</option>
                ${machineOptions}
            </select>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm description" disabled>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm category" disabled>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm location" disabled>
            <input type="hidden" name="location_id[]" class="location-id">
        </td>
        <td style="width: 50px;">
            <button type="button" class="btn btn-danger btn-sm remove-row" data-row="${rowCount}">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    document.getElementById('returnRows').appendChild(row);

    $(row).find('.machine-select').wrap('<span class="select2-sm"></span>').select2({
        theme: 'bootstrap-5', width: '100%', dropdownParent: $('body'),
        placeholder: '-- Select Machine --'
    });

    $(row).find('.machine-select').on('change', (e) => handleMachineChange(e, row));
    row.querySelector('.remove-row').addEventListener('click', removeRow);
}

function handleMachineChange(e, row) {
    const machineData = JSON.parse(e.target.options[e.target.selectedIndex].dataset.machine || '{}');

    if (machineData && machineData.serial_no) {
        row.querySelector('.description').value = machineData.description || '';
        row.querySelector('.category').value = machineData.category || '';
        row.querySelector('.location').value = machineData.location || '';
        row.querySelector('.location-id').value = machineData.location_id || '';
    }
}

function removeRow(e) {
    const row = e.currentTarget.dataset.row;
    document.getElementById('row' + row).remove();
    rowCount--;

    if (rowCount <= 0) {
        document.getElementById('submitBtn').hidden = true;
    }
}

document.getElementById('returnForm').addEventListener('submit', function(e) {
    e.preventDefault();

    if (!document.querySelector('input[name="date_received"]').value) {
        alert('Please select a date');
        return;
    }
    if (!document.querySelector('select[name="customer_id"]').value) {
        alert('Please select a client');
        return;
    }
    if (rowCount <= 0) {
        alert('Please add at least one machine');
        return;
    }

    this.submit();
});
</script>
@endsection
