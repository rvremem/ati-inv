@extends('layouts.app')

@section('title', 'Add Defective Part')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Add Defective Part</h2>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('defective-parts.store') }}">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-6">
                        <x-form-group
                            label="Part Number"
                            name="item_part_id"
                            type="select"
                            required
                        >
                            <option value="">-- Select Part --</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->part_no }} - {{ $item->description }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                    <div class="col-md-6">
                        <x-form-group
                            label="Serial Number"
                            name="serial_no"
                            type="text"
                        />
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <x-form-group
                            label="Quantity"
                            name="quantity"
                            type="number"
                            value="1"
                            required
                            min="1"
                        />
                    </div>
                    <div class="col-md-6">
                        <x-form-group
                            label="Location"
                            name="location_id"
                            type="select"
                            required
                        >
                            <option value="">-- Select Location --</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->code }}</option>
                            @endforeach
                        </x-form-group>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <x-form-group
                            label="Reason for Defect"
                            name="reason"
                            type="text"
                            required
                        />
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <x-form-group
                            label="Remarks"
                            name="remarks"
                            type="text"
                        />
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Defective Part
                    </button>
                </div>
            </form>
        </div>
    </div>
    <x-back-link :href="route('defective-parts.index')" />
</div>
@endsection
