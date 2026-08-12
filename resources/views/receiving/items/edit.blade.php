@extends('layouts.app')

@section('title', 'Edit Receiving Record')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Edit Receiving Record</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <form method="POST" action="{{ route('receiving.item-update', $receiving) }}">
                        @csrf @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Date Received"
                                    name="date_received"
                                    type="date"
                                    :value="$receiving->date_received->format('Y-m-d')"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="ATI PO"
                                    name="ati_po"
                                    type="text"
                                    :value="$receiving->ati_po"
                                />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Item Part"
                                    name="item_part_id"
                                    type="select"
                                    required
                                >
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}" {{ $receiving->item_part_id == $item->id ? 'selected' : '' }}>
                                            {{ $item->part_no }} - {{ $item->description }}
                                        </option>
                                    @endforeach
                                </x-form-group>
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Location"
                                    name="location_id"
                                    type="select"
                                    required
                                >
                                    @foreach ($locations as $loc)
                                        <option value="{{ $loc->id }}" {{ $receiving->location_id == $loc->id ? 'selected' : '' }}>
                                            {{ $loc->code }}
                                        </option>
                                    @endforeach
                                </x-form-group>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Quantity"
                                    name="quantity"
                                    type="number"
                                    :value="$receiving->quantity"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Serial No"
                                    name="serial_no"
                                    type="text"
                                    :value="$receiving->serial_no"
                                />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Supplier"
                                    name="supplier_id"
                                    type="select"
                                >
                                    <option value="">-- Select Supplier --</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ $receiving->supplier_id == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                    @endforeach
                                </x-form-group>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Status"
                                    name="status"
                                    type="select"
                                    required
                                >
                                    <option value="Receiving" {{ $receiving->status === 'Receiving' ? 'selected' : '' }}>Receiving</option>
                                    <option value="Return" {{ $receiving->status === 'Return' ? 'selected' : '' }}>Return</option>
                                </x-form-group>
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Remarks"
                                    name="return_remarks"
                                    type="text"
                                    :value="$receiving->return_remarks"
                                />
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update
                            </button>
                            <a href="{{ route('receiving.items') }}" class="btn btn-secondary">
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
