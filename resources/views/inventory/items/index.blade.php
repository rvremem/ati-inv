@extends('layouts.app')

@section('title', 'Item Parts')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Item Parts</h2>
        {{-- Engineers see Item Parts read-only. The controller guards the write
             endpoints too, since hiding buttons leaves routes open. --}}
        @if ($canManage)
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="resetForm()">
            <i class="fas fa-plus"></i> Add Item
        </button>
        @endif
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            {{-- no-datatable keeps the layout's auto-init from claiming this table;
                 it is initialised below in server-side mode instead. --}}
            <table id="itemsTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Part No</th>
                        <th>Description</th>
                        <th>Manufacturer</th>
                        <th>Type</th>
                        <th>Unit Cost</th>
                        <th>Status</th>
                        @if ($canManage)<th style="width:150px;">Actions</th>@endif
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Not rendered for read-only roles: nothing can open it, and it would
     otherwise publish the store/update endpoints to users who cannot use them. --}}
@if ($canManage)
<!-- Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Item Part</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="itemForm" method="POST" action="{{ route('inventory.item-store') }}" data-store-url="{{ route('inventory.item-store') }}" data-update-url-pattern="/inventory/items/">
                @csrf
                <input type="hidden" id="itemId" name="item_id">
                <input type="hidden" id="formMethod" name="_method" value="POST">
                <div class="modal-body">
                    <x-form-group
                        label="Part Number"
                        name="part_no"
                        type="text"
                        required
                    />

                    <x-form-group
                        label="Description"
                        name="description"
                        type="text"
                        required
                    />

                    <x-form-group
                        label="Client"
                        name="customer_id"
                        type="select"
                    >
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </x-form-group>

                    <x-form-group
                        label="Manufacturer"
                        name="manufacturer"
                        type="text"
                    />

                    <x-form-group
                        label="Model"
                        name="model"
                        type="text"
                    />

                    <x-form-group
                        label="Type"
                        name="type"
                        type="text"
                        required
                        help="e.g., Hardware, Software, Accessory"
                    />

                    <x-form-group
                        label="Unit Cost"
                        name="unit_cost"
                        type="number"
                        step="0.01"
                        required
                    />

                    <x-form-group
                        label="Minimum Stock Level"
                        name="min_stock"
                        type="number"
                        required
                    />

                    <x-form-group
                        label="Is Serialized"
                        name="is_serialized"
                        type="checkbox"
                        help="Check if items need serial number tracking"
                    />

                    <x-form-group
                        label="Status"
                        name="is_active"
                        type="select"
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </x-form-group>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
{{-- This block lives in @section('scripts') rather than inline in the content,
     because the layout loads jQuery/DataTables at the end of the body. --}}
<script>
@if ($canManage)
const form = document.getElementById('itemForm');
const storeUrl = form.dataset.storeUrl;
const updateUrlPattern = form.dataset.updateUrlPattern;
const deleteUrlPattern = '{{ url('inventory/items') }}/';
const CSRF = '{{ csrf_token() }}';
@endif

const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

const money = (d) => Number(d || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

@if ($canManage)
function resetForm() {
    document.getElementById('itemForm').reset();
    document.getElementById('itemId').value = '';
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('modalTitle').textContent = 'Add New Item Part';
    form.action = storeUrl;

    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

function editItem(item) {
    document.getElementById('itemId').value = item.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('modalTitle').textContent = 'Edit Item Part: ' + item.part_no;
    form.action = updateUrlPattern + item.id;

    document.querySelector('input[name="part_no"]').value = item.part_no;
    document.querySelector('input[name="description"]').value = item.description;
    document.querySelector('select[name="customer_id"]').value = item.customer_id || '';
    document.querySelector('input[name="manufacturer"]').value = item.manufacturer || '';
    document.querySelector('input[name="model"]').value = item.model || '';
    document.querySelector('input[name="type"]').value = item.type;
    document.querySelector('input[name="unit_cost"]').value = item.unit_cost;
    document.querySelector('input[name="min_stock"]').value = item.min_stock;
    document.querySelector('input[name="is_serialized"]').checked = item.is_serialized;
    document.querySelector('select[name="is_active"]').value = item.is_active ? '1' : '0';
}
@endif

$(function () {
    $('#itemsTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: '{{ route('inventory.items-data') }}',
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[0, 'asc']],
        pagingType: 'simple',
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
        language: {
            search: '',
            searchPlaceholder: 'Search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            processing: 'Loading...',
            paginate: { previous: '← Prev', next: 'Next →' }
        },
        columns: [
            { data: 'part_no', render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'description', render: esc },
            { data: 'manufacturer', render: (d) => esc(d || '-') },
            { data: 'type', render: esc },
            { data: 'unit_cost', className: 'text-end', render: money },
            { data: 'is_active', render: (d) => d
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>' },
            @if ($canManage)
            // Rows are rebuilt on every draw, so the edit payload rides along on
            // the button and the click is bound by delegation below.
            { data: null, orderable: false, searchable: false, render: (data, type, row) =>
                '<div class="btn-group btn-group-sm" role="group">'
                + '<button type="button" class="btn btn-warning edit-btn" data-bs-toggle="modal" data-bs-target="#itemModal" data-item=\'' + esc(JSON.stringify(row)) + '\'><i class="fas fa-edit"></i></button>'
                + '<form method="POST" action="' + deleteUrlPattern + row.id + '" style="display:inline;" onsubmit="return confirm(\'Are you sure?\')">'
                + '<input type="hidden" name="_token" value="' + CSRF + '">'
                + '<input type="hidden" name="_method" value="DELETE">'
                + '<button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>'
                + '</form></div>'
            }
            @endif
        ]
    });

    @if ($canManage)
    $('#itemsTable tbody').on('click', '.edit-btn', function () {
        editItem(JSON.parse(this.dataset.item));
    });
    @endif
});
</script>
@endsection
