@extends('layouts.app')

@section('title', 'Add Supplier')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Add New Supplier</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <form method="POST" action="{{ route('settings.supplier-store') }}">
                        @csrf

                        <x-form-group
                            label="Supplier Name"
                            name="name"
                            type="text"
                            required
                        />

                        <x-form-group
                            label="Address"
                            name="address"
                            type="text"
                        />

                        <x-form-group
                            label="Contact Number"
                            name="contact_no"
                            type="text"
                        />

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save
                            </button>
                            <a href="{{ route('settings.suppliers') }}" class="btn btn-secondary">
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
