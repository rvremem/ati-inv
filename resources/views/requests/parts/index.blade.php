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
                            label="Pick Up Date"
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

                <div class="table-responsive mb-3">
                    <table class="table table-bordered no-datatable" id="itemTable">
                        <thead class="table-light">
                            <tr>
                                <th>Part Number</th>
                                <th>Description</th>
                                <th>Serial / Available Qty</th>
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
        <td class="serial-avail-cell" data-row="${rowCount}">
            <!-- populated once a part is picked: serial dropdown or available qty -->
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

    $(row).find('.part-select').wrap('<span class="select2-sm"></span>').select2({
        theme: 'bootstrap-5', width: '100%', dropdownParent: $('body'),
        placeholder: '-- Select Part --'
    });

    $(row).find('.part-select').on('change', handlePartChange);
    row.querySelector('.remove-row').addEventListener('click', removeRow);
}

function handlePartChange(e) {
    const row = e.target.dataset.row;
    const itemId = e.target.value;
    const selectedItem = itemsData.find(item => item.id == itemId);
    const cell = document.querySelector(`.serial-avail-cell[data-row="${row}"]`);
    const quantityInput = document.querySelector(`input[name="quantity[]"][data-row="${row}"]`);

    if (!selectedItem) {
        cell.innerHTML = '';
        quantityInput.removeAttribute('max');
        return;
    }

    document.querySelector(`input[name="description[]"][data-row="${row}"]`).value = selectedItem.description;

    if (selectedItem.is_serialized) {
        // Serialized: show the serial picker, fetch its options.
        quantityInput.removeAttribute('max');
        cell.innerHTML = `
            <select name="serial_no[]" class="form-select form-select-sm serial-select" data-row="${row}">
                <option value="">-- Select Serial --</option>
            </select>
        `;

        fetch(`/api/item/${itemId}/available-serials`)
            .then(response => response.json())
            .then(data => {
                const serialSelect = cell.querySelector('.serial-select');
                data.serials.forEach(serial => {
                    const option = document.createElement('option');
                    option.value = serial;
                    option.textContent = serial;
                    serialSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching serials:', error));
    } else {
        // Not serialized: show on-hand quantity, fetched fresh so it always
        // matches Stock Levels rather than a stale figure on the part record.
        cell.innerHTML = `<span class="text-muted small">Loading…</span>`;

        fetch(`/api/item/${itemId}/available-quantity`)
            .then(response => response.json())
            .then(data => {
                const qty = data.available_quantity ?? 0;
                cell.innerHTML = `
                    <input type="text" class="form-control form-control-sm text-center" value="${qty}" disabled>
                `;
                quantityInput.setAttribute('max', qty);
            })
            .catch(error => {
                console.error('Error fetching available quantity:', error);
                cell.innerHTML = `<span class="text-danger small">Failed to load</span>`;
            });
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