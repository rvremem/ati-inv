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

// Every row sends a serial_no[], blank when it has none. The server pairs
// serial_no[i] with item_part_id[i] by position, so a row that sent nothing
// shifted every later serial onto the row before it.
const NO_SERIAL = '<input type="hidden" name="serial_no[]" value="">';

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
            ${NO_SERIAL}
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

    // A new part undoes whatever a previous part's serial locked in.
    quantityInput.readOnly = false;

    if (!selectedItem) {
        cell.innerHTML = NO_SERIAL;
        quantityInput.removeAttribute('max');
        syncSerialOptions();
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

        // One serial is one unit, so picking one fixes the quantity at 1.
        // readOnly rather than disabled, so the value is still submitted.
        // Clearing the serial hands the field back.
        cell.querySelector('.serial-select').addEventListener('change', function () {
            quantityInput.value = this.value ? 1 : '';
            quantityInput.readOnly = !!this.value;
            syncSerialOptions();
        });

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
                syncSerialOptions();
            })
            .catch(error => console.error('Error fetching serials:', error));
    } else {
        // Not serialized: show on-hand quantity, fetched fresh so it always
        // matches Stock Levels rather than a stale figure on the part record.
        cell.innerHTML = `<span class="text-muted small">Loading…</span>${NO_SERIAL}`;
        syncSerialOptions(); // this row may have held a serial until now

        fetch(`/api/item/${itemId}/available-quantity`)
            .then(response => response.json())
            .then(data => {
                const qty = data.available_quantity ?? 0;
                cell.innerHTML = `
                    <input type="text" class="form-control form-control-sm text-center" value="${qty}" disabled>
                    ${NO_SERIAL}
                `;
                quantityInput.setAttribute('max', qty);
            })
            .catch(error => {
                console.error('Error fetching available quantity:', error);
                cell.innerHTML = `<span class="text-danger small">Failed to load</span>${NO_SERIAL}`;
            });
    }
}

// A serial picked on one row is hidden from every other row's dropdown, so
// one unit cannot be requested twice in the same form. Re-run whenever a
// pick changes, a list loads, or a row goes away and frees its serial.
function syncSerialOptions() {
    const selects = [...document.querySelectorAll('#itemRows .serial-select')];
    selects.forEach(select => {
        const takenElsewhere = new Set(
            selects.filter(s => s !== select && s.value).map(s => s.value)
        );
        [...select.options].forEach(option => {
            const taken = option.value !== '' && takenElsewhere.has(option.value);
            option.hidden = taken;
            option.disabled = taken;
        });
    });
}

function removeRow(e) {
    const row = e.currentTarget.dataset.row;
    document.getElementById('row' + row).remove();
    syncSerialOptions();
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

    // Unserialised parts may not exceed what is available. Summed per part,
    // as the server does, so two rows of the same part are checked together.
    // The server refuses it anyway; this just says so before the page reloads
    // and the rows are lost.
    const wanted = {};
    const available = {};
    document.querySelectorAll('#itemRows tr').forEach(tr => {
        const qty = tr.querySelector('.quantity');
        if (!qty || !qty.hasAttribute('max')) return; // serialised, or still loading
        const partId = tr.querySelector('.part-select').value;
        wanted[partId] = (wanted[partId] || 0) + (parseInt(qty.value, 10) || 0);
        available[partId] = parseInt(qty.getAttribute('max'), 10);
    });
    for (const partId in wanted) {
        if (wanted[partId] > available[partId]) {
            const part = itemsData.find(item => item.id == partId);
            notify(`${part ? part.part_no : 'Part'}: requested ${wanted[partId]}, only ${available[partId]} available`, 'warning');
            return;
        }
    }

    this.submit();
});
</script>
@endsection