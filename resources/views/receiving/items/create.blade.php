@extends('layouts.app')

@section('title', 'Receive Parts')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Receive New Parts</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <form method="POST" action="{{ route('receiving.item-store') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Date Received"
                                    name="date_received"
                                    type="date"
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="ATI PO"
                                    name="ati_po"
                                    type="text"
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
                                        {{-- data-serialized drives the quantity lock below. --}}
                                        <option value="{{ $item->id }}" data-serialized="{{ $item->is_serialized ? 1 : 0 }}">{{ $item->part_no }} - {{ $item->description }}</option>
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
                                        <option value="{{ $loc->id }}">{{ $loc->code }}</option>
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
                                    required
                                />
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Serial No"
                                    name="serial_no"
                                    type="text"
                                />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Customer"
                                    name="customer_id"
                                    type="select"
                                    required
                                >
                                    <option value="">-- Select --</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                    @endforeach
                                </x-form-group>
                            </div>
                            <div class="col-md-6">
                                <x-form-group
                                    label="Supplier"
                                    name="supplier_id"
                                    type="select"
                                >
                                    <option value="">-- Select Supplier --</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </x-form-group>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-form-group
                                    label="Hub"
                                    name="hub_id"
                                    type="select"
                                >
                                    <option value="">-- Select Hub --</option>
                                    @foreach ($hubs as $hub)
                                        <option value="{{ $hub->id }}" @selected(old('hub_id') == $hub->id)>{{ $hub->hub_name }}</option>
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
                                    <option value="Receiving">Receiving</option>
                                    <option value="Return">Return</option>
                                </x-form-group>
                            </div>
                        </div>

                        <x-form-group
                            label="Remarks"
                            name="return_remarks"
                            type="text"
                        />

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Receive
                            </button>
                            <a href="{{ route('receiving.items') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>

                    <script>
                    // A serialised part is one physical unit, so its quantity is
                    // fixed at 1 and locked rather than typed. readOnly, not
                    // disabled — a disabled input is never submitted. The
                    // controller enforces the same rule regardless of this.
                    (function () {
                        const itemSelect = document.getElementById('item_part_id');
                        const qtyInput = document.getElementById('quantity');
                        if (!itemSelect || !qtyInput) { return; }

                        function syncQuantity() {
                            const option = itemSelect.options[itemSelect.selectedIndex];
                            const serialised = option && option.dataset.serialized === '1';

                            if (serialised) {
                                qtyInput.value = 1;
                                qtyInput.readOnly = true;
                                qtyInput.dataset.auto = '1';
                            } else {
                                qtyInput.readOnly = false;
                                if (qtyInput.dataset.auto === '1') {
                                    qtyInput.value = '';
                                    delete qtyInput.dataset.auto;
                                }
                            }
                        }

                        itemSelect.addEventListener('change', syncQuantity);
                        syncQuantity(); // the select already has a value on load
                    })();
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@include('partials.hub-locations')
@endsection
