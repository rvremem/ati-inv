@extends('layouts.app')

@section('title', 'Request Parts')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Request Parts</h2>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <form id="requestForm" method="POST" action="{{ route('requests.part-store') }}">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-6">
                        <x-form-group
                            label="Date Request"
                            name="pick_date"
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
                    <table class="table table-bordered no-datatable" id="itemTable">
                        <thead class="table-light">
                            <tr>
                                <th>Item Parts</th>
                                <th>Description</th>
                                <th>Location</th>
                                <th>Stock Quantity</th>
                                <th>Quantity</th>
                                <th>Remarks</th>
                                <th style="width: 50px;">
                                    <button type="button" id="addRowBtn" class="btn btn-success btn-sm">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="itemRows">
                        </tbody>
                    </table>
                </div>

                <div class="text-center">
                    <a href="{{ route('requests.parts') }}" class="btn btn-secondary me-2">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" id="submitBtn" class="btn btn-primary" hidden>
                        <i class="fas fa-save"></i> Request Item/s
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let rowCount = 0;
const itemsData = @json($items);
const locationsData = @json($locations);

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
                ${itemsData.map(item => `<option value="${item.id}">${item.part_no}</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="text" name="description[]" class="form-control form-control-sm description" data-row="${rowCount}" disabled>
        </td>
        <td>
            <select name="location_id[]" class="form-select form-select-sm location-select" data-row="${rowCount}" required>
                <option value="">-- Select Location --</option>
                ${locationsData.map(loc => `<option value="${loc.id}">${loc.code}</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="text" name="stock_qty[]" class="form-control form-control-sm stock-qty" data-row="${rowCount}" disabled>
        </td>
        <td>
            <input type="number" name="quantity[]" class="form-control form-control-sm quantity" data-row="${rowCount}" required>
        </td>
        <td>
            <input type="text" name="item_remarks[]" class="form-control form-control-sm item-remarks" data-row="${rowCount}">
        </td>
        <td style="width: 50px;">
            <button type="button" class="btn btn-danger btn-sm remove-row" data-row="${rowCount}">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    document.getElementById('itemRows').appendChild(row);
    initRowSelect2(row);

    $(row).find('.part-select').on('change', handlePartChange);
    $(row).find('.location-select').on('change', handleLocationChange);
    row.querySelector('.remove-row').addEventListener('click', removeRow);
}

function handlePartChange(e) {
    const row = e.target.dataset.row;
    const selectedItem = itemsData.find(item => item.id == e.target.value);

    if (selectedItem) {
        document.querySelector(`input[name="description[]"][data-row="${row}"]`).value = selectedItem.description;
    }
}

function handleLocationChange(e) {
    const row = e.target.dataset.row;
    const itemId = document.querySelector(`select[name="item_part_id[]"][data-row="${row}"]`).value;
    const locationId = e.target.value;

    if (itemId && locationId) {
        // Calculate stock quantity from receiving table
        // This would be an AJAX call in a real scenario
        const stockQtyInput = document.querySelector(`input[name="stock_qty[]"][data-row="${row}"]`);
        const quantityInput = document.querySelector(`input[name="quantity[]"][data-row="${row}"]`);
        const remarksInput = document.querySelector(`input[name="item_remarks[]"][data-row="${row}"]`);

        // For now, set as enabled - in real scenario, fetch from server
        quantityInput.disabled = false;
        remarksInput.disabled = false;

        // You can add AJAX call here to fetch stock qty:
        // fetch(`/api/stock/${itemId}/${locationId}`)
        //   .then(r => r.json())
        //   .then(data => stockQtyInput.value = data.quantity);
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

document.getElementById('requestForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // Validation
    if (!document.querySelector('input[name="pick_date"]').value) {
        alert('Please select a date');
        return;
    }
    if (!document.querySelector('select[name="customer_id"]').value) {
        alert('Please select a client');
        return;
    }
    if (rowCount <= 0) {
        alert('Please add at least one item');
        return;
    }

    this.submit();
});
</script>
@endsection
