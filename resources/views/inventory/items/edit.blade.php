@extends('layouts.app')

@section('title', 'Edit Item Part')

@section('content')
<div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;">
    <div class="container" style="max-width: 600px;">
        <div class="card">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit Item Part: {{ $item->part_no }}</h5>
                <a href="{{ route('inventory.items') }}" class="btn-close"></a>
            </div>
            <div class="card-body">
                <x-alert />

                    <form method="POST" action="{{ route('inventory.item-update', $item) }}">
                        @csrf @method('PUT')

                        <x-form-group
                            label="Part Number"
                            name="part_no"
                            type="text"
                            :value="$item->part_no"
                            required
                        />

                        <x-form-group
                            label="Description"
                            name="description"
                            type="text"
                            :value="$item->description"
                            required
                        />

                        <x-form-group
                            label="Manufacturer"
                            name="manufacturer"
                            type="text"
                            :value="$item->manufacturer"
                        />

                        <x-form-group
                            label="Model"
                            name="model"
                            type="text"
                            :value="$item->model"
                        />

                        <x-form-group
                            label="Type"
                            name="type"
                            type="text"
                            :value="$item->type"
                            required
                        />

                        <x-form-group
                            label="Warranty"
                            name="warranty"
                            type="text"
                            :value="$item->warranty"
                            help="e.g., 3 months, 1 year, 90 days"
                        />

                        {{-- Super Admin / Developer only. This one rendered
                             the stored cost into the page, so gating it is a
                             disclosure fix, not just a tidy-up. --}}
                        @if ($canSeeCost)
                        <x-form-group
                            label="Unit Cost"
                            name="unit_cost"
                            type="number"
                            step="0.01"
                            :value="$item->unit_cost"
                            required
                        />
                        @endif

                        <x-form-group
                            label="Minimum Stock Level"
                            name="min_stock"
                            type="number"
                            :value="$item->min_stock"
                            required
                        />

                        <x-form-group
                            label="Is Serialized"
                            name="is_serialized"
                            type="checkbox"
                            :value="$item->is_serialized"
                        />

                        <x-form-group
                            label="Status"
                            name="is_active"
                            type="select"
                            required
                        >
                            <option value="1" {{ $item->is_active ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ !$item->is_active ? 'selected' : '' }}>Inactive</option>
                        </x-form-group>

                        <x-form-group
                            label="Remarks"
                            name="remarks"
                            type="textarea"
                            :value="$item->remarks"
                        />

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update
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
