@extends('layouts.app')

@section('title', 'Edit Machine')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Edit Machine</h2>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <form id="machineForm" method="POST" action="{{ route('machines.update', $machine) }}">
                @csrf
                @method('PUT')

                <div class="row mb-3">
                    <div class="col-md-3">
                        <x-form-group
                            label="Date Received"
                            name="date_received"
                            type="date"
                            :value="$machine->date_received"
                            required
                        />
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Serial Number"
                            name="serial_no"
                            type="text"
                            :value="$machine->serial_no"
                            required
                        />
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Description"
                            name="description"
                            type="text"
                            :value="$machine->description"
                            required
                        />
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Model"
                            name="model"
                            type="text"
                            :value="$machine->model"
                        />
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <x-form-group
                            label="Category"
                            name="category_id"
                            type="select"
                        >
                            <option value="">-- Select Category --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected($machine->category_id == $category->id)>{{ $category->code }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Location"
                            name="location_id"
                            type="select"
                        >
                            <option value="">-- Select Location --</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected($machine->location_id == $location->id)>{{ $location->code }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Customer"
                            name="customer_id"
                            type="select"
                        >
                            <option value="">-- Select Customer --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected($machine->customer_id == $customer->id)>{{ $customer->name }}</option>
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
                                <option value="{{ $supplier->id }}" @selected($machine->supplier_id == $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                </div>

                <h4 class="mb-3 mt-4">Machine Parts</h4>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered" id="partsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Part Number</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Remarks</th>
                                <th style="width: 50px;">
                                    <button type="button" id="addPartBtn" class="btn btn-success btn-sm">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="partsRows">
                        </tbody>
                    </table>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Machine
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let partRowCount = 0;
const itemsData = @json($items);
const existingParts = @json($machine->parts ?? []);

document.getElementById('addPartBtn').addEventListener('click', function() {
    addPartRow();
});

function addPartRow(itemPartId = '', description = '', quantity = '', remarks = '') {
    partRowCount++;
    const row = document.createElement('tr');
    row.id = 'part-row' + partRowCount;
    row.innerHTML = `
        <td>
            <select name="part_id[]" class="form-select form-select-sm part-select" data-row="${partRowCount}" required>
                <option value="">-- Select Part --</option>
                ${itemsData.map(item => `<option value="${item.id}" ${item.id == itemPartId ? 'selected' : ''}>${item.part_no}</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="text" name="part_description[]" class="form-control form-control-sm part-description" data-row="${partRowCount}" value="${description}" disabled>
        </td>
        <td>
            <input type="number" name="part_quantity[]" class="form-control form-control-sm" data-row="${partRowCount}" value="${quantity}" required min="1">
        </td>
        <td>
            <input type="text" name="part_remarks[]" class="form-control form-control-sm" value="${remarks}">
        </td>
        <td style="width: 50px;">
            <button type="button" class="btn btn-danger btn-sm remove-part-row" data-row="${partRowCount}">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    document.getElementById('partsRows').appendChild(row);

    $(row).find('.part-select').wrap('<span class="select2-sm"></span>').select2({
        theme: 'bootstrap-5', width: '100%', dropdownParent: $('body'),
        placeholder: '-- Select Part --'
    });

    $(row).find('.part-select').on('change', handlePartChange);
    row.querySelector('.remove-part-row').addEventListener('click', removePartRow);
}

function handlePartChange(e) {
    const row = e.target.dataset.row;
    const selectedItem = itemsData.find(item => item.id == e.target.value);

    if (selectedItem) {
        document.querySelector(`input[name="part_description[]"][data-row="${row}"]`).value = selectedItem.description;
    }
}

function removePartRow(e) {
    const row = e.currentTarget.dataset.row;
    document.getElementById('part-row' + row).remove();
    partRowCount--;
}

// Load existing parts
existingParts.forEach(part => {
    addPartRow(part.item_part_id, part.item_part?.description || '', part.quantity, part.remarks);
});

document.getElementById('machineForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // Validation
    if (!document.querySelector('input[name="serial_no"]').value) {
        notify('Please enter serial number', 'warning');
        return;
    }
    if (!document.querySelector('input[name="description"]').value) {
        notify('Please enter description', 'warning');
        return;
    }

    this.submit();
});
</script>
@endsection
