@extends('layouts.app')

@section('title', 'Add Parts to Machine')

@section('content')
<div class="container-fluid">
    <div class="mb-4">
        <a href="{{ route('receiving.machines') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <h2 class="mt-3">Add Parts to {{ $machine->serial_no }}</h2>
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('machines.store-part', $machine) }}">
                @csrf

                <div class="table-responsive mb-3">
                    <table class="table table-bordered no-datatable" id="partsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Part Number</th>
                                <th>Description</th>
                                <th style="width: 200px;">Serial Number</th>
                                <th style="width: 90px;">Quantity</th>
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
                        <i class="fas fa-save"></i> Save Parts
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let partRowCount = 0;
const itemsData = @json($items);

document.getElementById('addPartBtn').addEventListener('click', function() {
    addPartRow();
});

function addPartRow() {
    partRowCount++;
    const row = document.createElement('tr');
    row.id = 'part-row' + partRowCount;
    row.innerHTML = `
        <td>
            <select name="item_part_id[]" class="form-select form-select-sm part-select" data-row="${partRowCount}" required>
                <option value="">-- Select Part --</option>
                ${itemsData.map(item => `<option value="${item.id}">${item.part_no}</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="text" name="description[]" class="form-control form-control-sm part-description" data-row="${partRowCount}" disabled>
        </td>
        <td>
            <input type="text" name="serial_no[]" class="form-control form-control-sm serial-no" data-row="${partRowCount}" placeholder="N/A" disabled>
        </td>
        <td>
            <input type="number" name="quantity[]" class="form-control form-control-sm" data-row="${partRowCount}" required min="1" step="1">
        </td>
        <td>
            <input type="text" name="remarks[]" class="form-control form-control-sm">
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
    const serialInput = document.querySelector(`input[name="serial_no[]"][data-row="${row}"]`);

    if (selectedItem) {
        document.querySelector(`input[name="description[]"][data-row="${row}"]`).value = selectedItem.description;
        if (selectedItem.is_serialized) {
            serialInput.disabled = false;
            serialInput.placeholder = 'Enter serial';
        } else {
            serialInput.disabled = true;
            serialInput.value = '';
            serialInput.placeholder = 'N/A';
        }
    }
}

function removePartRow(e) {
    const row = e.currentTarget.dataset.row;
    document.getElementById('part-row' + row).remove();
    partRowCount--;
}
</script>
@endsection
