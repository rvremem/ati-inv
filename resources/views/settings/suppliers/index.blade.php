@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Suppliers</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="resetSupplierForm()">
            <i class="fas fa-plus"></i> Add Supplier
        </button>
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <x-data-table :headers="['Name', 'Address', 'Contact', 'Status']" :rows="$suppliers">
                @foreach ($suppliers as $supplier)
                    <tr>
                        <td><strong>{{ $supplier->name }}</strong></td>
                        <td>{{ $supplier->address ?? '-' }}</td>
                        <td>{{ $supplier->contact_no ?? '-' }}</td>
                        <td>
                            @if ($supplier->is_active)
                                <x-status-badge status="Active" />
                            @else
                                <x-status-badge status="Inactive" />
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-warning edit-supplier-btn" data-bs-toggle="modal" data-bs-target="#supplierModal" data-supplier="{{ $supplier->toJson() }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="{{ route('settings.supplier-delete', $supplier) }}" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</div>

<!-- Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supplierModalTitle">Add New Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="supplierForm" method="POST" action="{{ route('settings.supplier-store') }}" data-store-url="{{ route('settings.supplier-store') }}" data-update-url-pattern="/settings/suppliers/">
                @csrf
                <input type="hidden" id="supplierId" name="supplier_id">
                <input type="hidden" id="supplierFormMethod" name="_method" value="POST">
                <div class="modal-body">
                    <x-form-group
                        label="Supplier Name"
                        name="name"
                        type="text"
                        required
                    />

                    <x-form-group
                        label="Address"
                        name="address"
                        type="text"
                    />

                    <x-form-group
                        label="Contact Number"
                        name="contact_no"
                        type="text"
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

<script>
const supplierForm = document.getElementById('supplierForm');
const supplierStoreUrl = supplierForm.dataset.storeUrl;
const supplierUpdateUrlPattern = supplierForm.dataset.updateUrlPattern;

document.querySelectorAll('.edit-supplier-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const supplierData = JSON.parse(this.dataset.supplier);
        editSupplier(supplierData);
    });
});

function resetSupplierForm() {
    document.getElementById('supplierForm').reset();
    document.getElementById('supplierId').value = '';
    document.getElementById('supplierFormMethod').value = 'POST';
    document.getElementById('supplierModalTitle').textContent = 'Add New Supplier';
    supplierForm.action = supplierStoreUrl;

    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

function editSupplier(supplier) {
    document.getElementById('supplierId').value = supplier.id;
    document.getElementById('supplierFormMethod').value = 'PUT';
    document.getElementById('supplierModalTitle').textContent = 'Edit Supplier: ' + supplier.name;
    supplierForm.action = supplierUpdateUrlPattern + supplier.id;

    document.querySelector('input[name="name"]').value = supplier.name;
    document.querySelector('input[name="address"]').value = supplier.address || '';
    document.querySelector('input[name="contact_no"]').value = supplier.contact_no || '';
    document.querySelector('select[name="is_active"]').value = supplier.is_active ? '1' : '0';
}
</script>
@endsection
