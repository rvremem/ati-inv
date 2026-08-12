@extends('layouts.app')

@section('title', 'Create Machine')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Create Machine</h2>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <form id="machineForm" method="POST" action="{{ route('machines.store') }}">
                @csrf

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
                        >
                            <option value="">-- Select Client --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
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
                                <option value="{{ $location->id }}">{{ $location->code }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                    <!-- <div class="col-md-3">
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
                    </div> -->
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <x-form-group
                            label="Serial Number"
                            name="serial_no"
                            type="text"
                            required
                        />
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Description"
                            name="description"
                            type="text"
                            required
                        />
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Model"
                            name="model"
                            type="text"
                        />
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Category/Machine Type"
                            name="category_id"
                            type="select"
                        >
                            <option value="">-- Select Category --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->code }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                </div>

                <div class="row mb-3">
                    
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Machine
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('machineForm').addEventListener('submit', function(e) {
    e.preventDefault();

    if (!document.querySelector('input[name="serial_no"]').value) {
        alert('Please enter serial number');
        return;
    }
    if (!document.querySelector('input[name="description"]').value) {
        alert('Please enter description');
        return;
    }

    this.submit();
});
</script>
@endsection
