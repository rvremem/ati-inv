@extends('layouts.app')

@section('title', 'Add Location')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Add New Location</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <form method="POST" action="{{ route('inventory.location-store') }}">
                        @csrf

                        <x-form-group
                            label="Location Code"
                            name="code"
                            type="text"
                            required
                            help="e.g., HO, CEBU, DAVAO"
                        />

                        <x-form-group
                            label="Description"
                            name="description"
                            type="text"
                            required
                            help="Full location name"
                        />

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save
                            </button>
                            <a href="{{ route('inventory.locations') }}" class="btn btn-secondary">
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
