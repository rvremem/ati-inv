@extends('layouts.app')

@section('title', 'Create Machine Request')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Create Machine Request</h2>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('requests.machine-store') }}">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-3">
                        <x-form-group
                            label="Pick Up Date"
                            name="pick_date"
                            type="date"
                            required
                        />
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Machine"
                            name="machine_id"
                            type="select"
                            required
                        >
                            <option value="">-- Select Machine --</option>
                            @foreach($machines as $machine)
                                <option value="{{ $machine->id }}" @selected($selectedMachineId == $machine->id)>
                                    {{ $machine->serial_no }} - {{ $machine->description }}
                                </option>
                            @endforeach
                        </x-form-group>
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Customer"
                            name="customer_id"
                            type="select"
                            required
                        >
                            <option value="">-- Select Customer --</option>
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
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <x-form-group
                            label="MRIS Number"
                            name="mris_no"
                            type="text"
                        />
                    </div>
                    <div class="col-md-3">
                        <x-form-group
                            label="Purpose"
                            name="purpose"
                            type="text"
                        />
                    </div>
                    <div class="col-md-6">
                        <x-form-group
                            label="Description"
                            name="description"
                            type="text"
                        />
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <x-form-group
                            label="Remarks"
                            name="remarks"
                            type="textarea"
                        />
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Request
                    </button>
                    <a href="{{ route('requests.machines') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
