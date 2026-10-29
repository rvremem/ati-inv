@extends('layouts.app')

@section('title', 'Edit Maintenance Contract')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Edit Maintenance Contract</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <form method="POST" action="{{ route('maintenance.update', $contract) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Account Manager"
                                    name="account_manager"
                                    type="text"
                                    :value="$contract->account_manager"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Equipment Type"
                                    name="equipment_type"
                                    type="text"
                                    :value="$contract->equipment_type"
                                    required
                                />
                            </div>
                        </div>

                        <x-form-group
                            label="Customer"
                            name="customer"
                            type="text"
                            :value="$contract->customer"
                            required
                        />

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="PO Number"
                                    name="po_no"
                                    type="text"
                                    :value="$contract->po_no"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Sites"
                                    name="sites"
                                    type="text"
                                    :value="$contract->sites"
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
                                    :value="$contract->brand"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Serial Number"
                                    name="serial_no"
                                    type="text"
                                    :value="$contract->serial_no"
                                    required
                                />
                            </div>
                        </div>

                        <x-form-group
                            label="Description"
                            name="description"
                            type="text"
                            :value="$contract->description"
                            required
                        />

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Location"
                                    name="location"
                                    type="text"
                                    :value="$contract->location"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Company"
                                    name="company"
                                    type="text"
                                    :value="$contract->company"
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
                                    :value="$contract->branch"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Coverage Months"
                                    name="coverage_months"
                                    type="number"
                                    :value="$contract->coverage_months"
                                    required
                                />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                {{-- start_date/end_date are cast to dates, so they need
                                     Y-m-d for a native date input. --}}
                                <x-form-group
                                    label="Start Date"
                                    name="start_date"
                                    type="date"
                                    :value="$contract->start_date?->format('Y-m-d')"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="End Date"
                                    name="end_date"
                                    type="date"
                                    :value="$contract->end_date?->format('Y-m-d')"
                                    required
                                />
                            </div>
                        </div>

                        {{-- A select rather than a checkbox: an unchecked checkbox submits
                             nothing, so is_active would never be able to go from 1 to 0. --}}
                        <x-form-group
                            label="Status"
                            name="is_active"
                            type="select"
                            required
                        >
                            <option value="1" @selected(old('is_active', (int) $contract->is_active) == 1)>Active</option>
                            <option value="0" @selected(old('is_active', (int) $contract->is_active) == 0)>Inactive</option>
                        </x-form-group>

                        <x-form-group
                            label="Remarks"
                            name="remarks"
                            type="textarea"
                            :value="$contract->remarks"
                        />

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update
                            </button>
                            <a href="{{ route('maintenance.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            {{-- The delete button lives in its own form below (forms
                                 cannot nest) and is wired to it via the form attribute. --}}
                            <button type="submit" form="deleteContractForm" class="btn btn-outline-danger ms-auto">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </form>

                    <form id="deleteContractForm" method="POST" action="{{ route('maintenance.destroy', $contract) }}"
                          onsubmit="return confirm('Delete this maintenance contract? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
