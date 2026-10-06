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
                            required />
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="ATI Purchase Order Number"
                            name="ati_po"
                            type="text" />
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Client"
                            name="customer_id"
                            type="select"
                            required>
                            <option value="">-- Select Client --</option>
                            @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                    <div class="col-md-3">
                        {{-- Locked to the selected parts' supplier; unlocked only
                             when none of them has one on file. See syncSupplier(). --}}
                        <x-form-group
                            label="Supplier"
                            name="supplier_id"
                            type="select">
                            <option value="">-- Select Supplier --</option>
                            @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <x-form-group
                            label="Status"
                            name="status"
                            type="select"
                            required>
                            <option value="">-- Select Status --</option>
                            <option value="Receiving">Receiving</option>
                            <option value="Old Stock">Old Stock</option>
                        </x-form-group>
                    </div>
                    <div class="col-md-4">
                        <x-form-group
                            label="Hub"
                            name="hub_id"
                            type="select">
                            <option value="">-- Select Hub --</option>
                            @foreach($hubs as $hub)
                            <option value="{{ $hub->id }}" @selected(old('hub_id')==$hub->id)>{{ $hub->hub_name }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                    <div class="col-md-4">
                        <x-form-group
                            label="Remarks"
                            name="remarks"
                            type="text" />
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

@include('partials.hub-locations')

<script>
    // Numbers rows for their ids only. It never goes down: reusing a number after
    // a removal gave two rows the same data-row, and a part picked on one filled
    // in the other.
    let rowCount = 0;
    const itemsData = @json($items);

    const supplierSelect = document.getElementById('supplier_id');
    // Locked until a part is picked. Plain DOM, not jQuery: this runs before the
    // layout loads jQuery, and select2 reads the disabled state when it starts.
    supplierSelect.disabled = true;

    const rowsLeft = () => document.querySelectorAll('#itemRows tr').length;

    const supplierName = (id) =>
        supplierSelect.querySelector(`option[value="${id}"]`)?.textContent.trim() || 'another supplier';

    /** Suppliers named by the parts picked so far, optionally leaving one row out. */
    function partSuppliers(exceptRow = null) {
        const ids = new Set();

        document.querySelectorAll('select.part-select').forEach(sel => {
            if (sel.dataset.row === exceptRow) {
                return;
            }

            const item = itemsData.find(i => i.id == sel.value);
            if (item?.supplier_id) {
                ids.add(String(item.supplier_id));
            }
        });

        return [...ids];
    }

    /**
     * Keeps Supplier in step with the parts below:
     *   no part picked yet             -> locked and empty
     *   a picked part has a supplier   -> locked to that supplier
     *   parts picked, none has one     -> unlocked, to be chosen by hand
     */
    function syncSupplier() {
        const [fromParts] = partSuppliers();
        const anyPicked = [...document.querySelectorAll('select.part-select')].some(sel => sel.value);

        if (fromParts) {
            supplierSelect.value = fromParts;
            supplierSelect.dataset.auto = '1';
            supplierSelect.disabled = true;
        } else {
            // Only clear a supplier this code filled in, so one picked by hand
            // survives adding another part with no supplier on file.
            if (supplierSelect.dataset.auto === '1') {
                supplierSelect.value = '';
                delete supplierSelect.dataset.auto;
            }
            supplierSelect.disabled = !anyPicked;
        }

        $(supplierSelect).trigger('change.select2');
    }

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
                ${locationOptionsHtml(document.getElementById('hub_id')?.value || '')}
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

        // One batch, one supplier: the header Supplier applies to every line.
        if (selectedItem?.supplier_id) {
            const others = partSuppliers(row);

            if (others.length && !others.includes(String(selectedItem.supplier_id))) {
                notify(`${selectedItem.part_no} is from ${supplierName(selectedItem.supplier_id)}, ` +
                    `but this batch is from ${supplierName(others[0])}.\n` +
                    'Receive it in a separate batch.', 'warning');
                // Re-enters this handler with no part, which re-syncs Supplier.
                $(e.target).val('').trigger('change');
                return;
            }
        }

        const serialInput = document.querySelector(`input[name="serial_no[]"][data-row="${row}"]`);
        const qtyInput = document.querySelector(`input[name="quantity[]"][data-row="${row}"]`);

        if (!selectedItem) {
            // Back to a blank row, including a quantity a serialised part locked.
            document.querySelector(`input[name="description[]"][data-row="${row}"]`).value = '';
            serialInput.style.display = 'block';
            serialInput.required = false;
            qtyInput.readOnly = false;
            if (qtyInput.dataset.auto === '1') {
                qtyInput.value = '';
                delete qtyInput.dataset.auto;
            }
        }

        if (selectedItem) {
            document.querySelector(`input[name="description[]"][data-row="${row}"]`).value = selectedItem.description;

            if (selectedItem.is_serialized) {
                serialInput.style.display = 'block';
                serialInput.required = true;

                // One serial is one unit, so there is nothing to type. readOnly
                // rather than disabled — a disabled field is not submitted at all.
                // The controller enforces this too.
                qtyInput.value = 1;
                qtyInput.readOnly = true;
                qtyInput.dataset.auto = '1';
            } else {
                serialInput.style.display = 'none';
                serialInput.required = false;

                qtyInput.readOnly = false;
                // Only clear a quantity this code filled in, so switching between
                // two non-serialised parts does not wipe what was typed.
                if (qtyInput.dataset.auto === '1') {
                    qtyInput.value = '';
                    delete qtyInput.dataset.auto;
                }
            }
        }

        syncSupplier();
    }

    function removeRow(e) {
        const row = e.currentTarget.dataset.row;
        document.getElementById('row' + row).remove();

        if (rowsLeft() === 0) {
            document.getElementById('submitBtn').hidden = true;
        }

        // The removed part may have been the one setting Supplier.
        syncSupplier();
    }

    document.getElementById('receivingForm').addEventListener('submit', function(e) {
        e.preventDefault();

        // Validation
        if (!document.querySelector('input[name="date_received"]').value) {
            notify('Please select a date', 'warning');
            return;
        }
        if (!document.querySelector('select[name="customer_id"]').value) {
            notify('Please select a client', 'warning');
            return;
        }
        if (rowsLeft() === 0) {
            notify('Please add at least one item', 'warning');
            return;
        }

        // A disabled field is not submitted at all. The server takes the supplier
        // from the parts anyway; this carries a hand-picked one when they have none.
        supplierSelect.disabled = false;

        this.submit();
    });
</script>
@endsection