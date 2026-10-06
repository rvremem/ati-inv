@extends('layouts.app')

@section('title', 'Return Parts')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Return Parts</h2>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <form id="returnForm" method="POST" action="{{ route('receiving.return-parts-store') }}">
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
                    <div class="col-md-3">
                        <x-form-group
                            label="Hub"
                            name="hub_id"
                            type="select"
                        >
                            <option value="">-- Select Hub --</option>
                            @foreach($hubs as $hub)
                                <option value="{{ $hub->id }}" @selected(old('hub_id') == $hub->id)>{{ $hub->hub_name }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                    <div class="col-md-9">
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
                                <th>Part Number</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Location</th>
                                <th>Serial Number</th>
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
                    <a href="{{ route('receiving.return-parts') }}" class="btn btn-secondary me-2">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" id="submitBtn" class="btn btn-primary" hidden>
                        <i class="fas fa-save"></i> Return Item/s
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let rowCount = 0;
let allParts = [];

// Fetch all parts on page load
fetch('/api/all-parts')
    .then(response => response.json())
    .then(data => {
        allParts = data.parts;
    })
    .catch(error => console.error('Error fetching parts:', error));

document.getElementById('addRowBtn').addEventListener('click', function() {
    addRow();
    document.getElementById('submitBtn').hidden = false;
});

function addRow() {
    rowCount++;
    const row = document.createElement('tr');
    row.id = 'row' + rowCount;
    row.innerHTML = `
        <td>
            <select name="item_part_id[]" class="form-select form-select-sm part-select" data-row="${rowCount}" required>
                <option value="">-- Select Part --</option>
                ${allParts.map(p => `<option value="${p.id}">${p.part_no}</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm description" disabled>
        </td>
        <td>
            <input type="number" name="quantity[]" class="form-control form-control-sm quantity" required min="1">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm location" disabled>
            <input type="hidden" name="location_id[]" class="location-id">
        </td>
        <td>
            <select name="serial_no[]" class="form-select form-select-sm serial-select" data-row="${rowCount}" required>
                <option value="">-- Select Serial --</option>
            </select>
        </td>
        <td style="width: 50px;">
            <button type="button" class="btn btn-danger btn-sm remove-row" data-row="${rowCount}">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    document.getElementById('returnRows').appendChild(row);

    // Select2 on part-select only; serial-select stays plain (populated dynamically)
    $(row).find('.part-select').wrap('<span class="select2-sm"></span>').select2({
        theme: 'bootstrap-5', width: '100%', dropdownParent: $('body'),
        placeholder: '-- Select Part --'
    });

    $(row).find('.part-select').on('change', (e) => handlePartChange(e, row));
    row.querySelector('.serial-select').addEventListener('change', (e) => handleSerialChange(e, row));
    row.querySelector('.remove-row').addEventListener('click', removeRow);
}

function handlePartChange(e, row) {
    const partId = e.target.value;
    const part = allParts.find(p => p.id == partId);

    if (part) {
        row.querySelector('.description').value = part.description || '';

        // A serialised part comes back one unit at a time, so the quantity is
        // fixed at 1 and locked. The controller enforces the same rule.
        const qtyInput = row.querySelector('.quantity');
        if (part.is_serialized) {
            qtyInput.value = 1;
            qtyInput.readOnly = true;
            qtyInput.dataset.auto = '1';
        } else {
            qtyInput.readOnly = false;
            if (qtyInput.dataset.auto === '1') {
                qtyInput.value = '';
                delete qtyInput.dataset.auto;
            }
        }
    }

    // Fetch serials for this part with status 'Out'
    fetch(`/api/item/${partId}/returnable-serials`)
        .then(response => response.json())
        .then(data => {
            const serialSelect = row.querySelector('.serial-select');
            serialSelect.innerHTML = '<option value="">-- Select Serial --</option>';

            data.serials.forEach(serial => {
                const option = document.createElement('option');
                option.value = serial.serial_no;
                option.dataset.serial = JSON.stringify(serial);
                option.textContent = serial.serial_no;
                serialSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error fetching serials:', error));
}

function handleSerialChange(e, row) {
    const serialData = JSON.parse(e.target.options[e.target.selectedIndex].dataset.serial || '{}');

    const qtyInput = row.querySelector('.quantity');
    // Don't let the receiving row's stored quantity overwrite the locked 1 on a
    // serialised part — that value can be anything on legacy rows.
    if (qtyInput.dataset.auto !== '1') {
        qtyInput.value = serialData.quantity || '';
    }

    row.querySelector('.location').value = serialData.location || '';
    row.querySelector('.location-id').value = serialData.location_id || '';
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
        notify('Please select a date', 'warning');
        return;
    }
    if (!document.querySelector('select[name="customer_id"]').value) {
        notify('Please select a client', 'warning');
        return;
    }
    if (rowCount <= 0) {
        notify('Please add at least one item', 'warning');
        return;
    }

    this.submit();
});
</script>
@endsection
