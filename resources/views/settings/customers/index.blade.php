@extends('layouts.app')

@section('title', 'Customers')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Customers</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('settings.customer-batch-upload') }}" class="btn btn-outline-primary">
                <i class="fas fa-file-upload"></i> Batch Upload
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetCustomerForm()">
                <i class="fas fa-plus"></i> Add Customer
            </button>
        </div>
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <x-data-table :headers="['Name', 'Status']" :rows="$customers">
                @foreach ($customers as $customer)
                    <tr>
                        <td><strong>{{ $customer->name }}</strong></td>
                        <td>
                            @if ($customer->is_active)
                                <x-status-badge status="Active" />
                            @else
                                <x-status-badge status="Inactive" />
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-warning edit-customer-btn" data-bs-toggle="modal" data-bs-target="#customerModal" data-customer="{{ $customer->toJson() }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="{{ route('settings.customer-delete', $customer) }}" style="display:inline;">
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

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalTitle">Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="customerForm" method="POST" action="{{ route('settings.customer-store') }}" data-store-url="{{ route('settings.customer-store') }}" data-update-url-pattern="/settings/customers/">
                @csrf
                <input type="hidden" id="customerId" name="customer_id">
                <input type="hidden" id="customerFormMethod" name="_method" value="POST">
                <div class="modal-body">
                    <x-form-group
                        label="Customer Name"
                        name="name"
                        type="text"
                        required
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
const customerForm = document.getElementById('customerForm');
const customerStoreUrl = customerForm.dataset.storeUrl;
const customerUpdateUrlPattern = customerForm.dataset.updateUrlPattern;

document.querySelectorAll('.edit-customer-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const customerData = JSON.parse(this.dataset.customer);
        editCustomer(customerData);
    });
});

function resetCustomerForm() {
    document.getElementById('customerForm').reset();
    document.getElementById('customerId').value = '';
    document.getElementById('customerFormMethod').value = 'POST';
    document.getElementById('customerModalTitle').textContent = 'Add New Customer';
    customerForm.action = customerStoreUrl;

    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

function editCustomer(customer) {
    document.getElementById('customerId').value = customer.id;
    document.getElementById('customerFormMethod').value = 'PUT';
    document.getElementById('customerModalTitle').textContent = 'Edit Customer: ' + customer.name;
    customerForm.action = customerUpdateUrlPattern + customer.id;

    document.querySelector('input[name="name"]').value = customer.name;
    document.querySelector('select[name="is_active"]').value = customer.is_active ? '1' : '0';
}
</script>
@endsection
