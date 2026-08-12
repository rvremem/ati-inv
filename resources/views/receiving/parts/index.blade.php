@extends('layouts.app')

@section('title', 'Receiving Parts')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Receiving Parts</h2>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <form id="receivingForm" method="POST" action="{{ route('receiving.items-batch-store') }}">
                @csrf

                {{-- Four across, matching atiis. The PO, client and supplier are
                     captured once here and apply to every line below. --}}
                <div class="row mb-3">
                    <div class="col-md-3">
                        <x-form-group
                            label="Date Received"
                            name="date_received"
                            type="date"
                            required
                        />
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="ATI Purchase Order Number"
                            name="ati_po"
                            type="text"
                        />
                    </div>
                    <div class="col-md-3">
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
                    <div class="col-md-3">
                        <x-form-group
                            label="Supplier"
                            name="supplier_id"
                            type="select"
                        >
                            <option value="">-- Select Supplier --</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
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
                        <tbody id="itemRows">
                        </tbody>
                    </table>
                </div>

                <div class="text-center">
                    <a href="{{ route('receiving.items') }}" class="btn btn-secondary me-2">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" id="submitBtn" class="btn btn-primary" hidden>
                        <i class="fas fa-save"></i> Receive Item/s
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
                ${itemsData.map(item => `<option value="${item.id}" data-serial="${item.is_serialized}">${item.part_no}</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="text" name="description[]" class="form-control form-control-sm description" data-row="${rowCount}" disabled>
        </td>
        <td>
            <input type="number" name="quantity[]" class="form-control form-control-sm quantity" data-row="${rowCount}" required>
        </td>
        <td>
            <select name="location_id[]" class="form-select form-select-sm" required>
                <option value="">-- Select Location --</option>
                ${locationsData.map(loc => `<option value="${loc.id}">${loc.code}</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="text" name="serial_no[]" class="form-control form-control-sm serial-no" data-row="${rowCount}">
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
    row.querySelector('.remove-row').addEventListener('click', removeRow);
}

function handlePartChange(e) {
    const row = e.target.dataset.row;
    const selectedItem = itemsData.find(item => item.id == e.target.value);

    if (selectedItem) {
        document.querySelector(`input[name="description[]"][data-row="${row}"]`).value = selectedItem.description;

        const serialInput = document.querySelector(`input[name="serial_no[]"][data-row="${row}"]`);
        if (selectedItem.is_serialized) {
            serialInput.style.display = 'block';
            serialInput.required = true;
        } else {
            serialInput.style.display = 'none';
            serialInput.required = false;
        }
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

document.getElementById('receivingForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // Validation
    if (!document.querySelector('input[name="date_received"]').value) {
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
