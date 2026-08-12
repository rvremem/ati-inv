@extends('layouts.app')

@section('title', 'Create Maintenance Contract')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Create New Maintenance Contract</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <form method="POST" action="{{ route('maintenance.store') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Account Manager"
                                    name="account_manager"
                                    type="text"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Equipment Type"
                                    name="equipment_type"
                                    type="text"
                                    required
                                />
                            </div>
                        </div>

                        <x-form-group
                            label="Customer"
                            name="customer_id"
                            type="select"
                            required
                        >
                            @foreach ($customers as $cust)
                                <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                            @endforeach
                        </x-form-group>

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="PO Number"
                                    name="po_no"
                                    type="text"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Sites"
                                    name="sites"
                                    type="text"
                                    required
                                />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Brand"
                                    name="brand"
                                    type="text"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Serial Number"
                                    name="serial_no"
                                    type="text"
                                    required
                                />
                            </div>
                        </div>

                        <x-form-group
                            label="Description"
                            name="description"
                            type="text"
                            required
                        />

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Location"
                                    name="location"
                                    type="text"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Company"
                                    name="company"
                                    type="text"
                                    required
                                />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Branch"
                                    name="branch"
                                    type="text"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Coverage Months"
                                    name="coverage_months"
                                    type="number"
                                    required
                                />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Start Date"
                                    name="start_date"
                                    type="date"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="End Date"
                                    name="end_date"
                                    type="date"
                                    required
                                />
                            </div>
                        </div>

                        <x-form-group
                            label="Remarks"
                            name="remarks"
                            type="textarea"
                        />

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create
                            </button>
                            <a href="{{ route('maintenance.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
