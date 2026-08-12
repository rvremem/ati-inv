@extends('layouts.app')

@section('title', 'Add Item Part')

@section('content')
<div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;">
    <div class="container" style="max-width: 600px;">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Add New Item Part</h5>
                <a href="{{ route('inventory.items') }}" class="btn-close btn-close-white"></a>
            </div>
            <div class="card-body">
                <x-alert />

                    <form method="POST" action="{{ route('inventory.item-store') }}">
                        @csrf

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
                            label="Remarks"
                            name="remarks"
                            type="textarea"
                        />

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save
                            </button>
                            <a href="{{ route('inventory.items') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
            </div>
        </div>
    </div>
</div>
@endsection
