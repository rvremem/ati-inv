@extends('layouts.app')

@section('title', 'Batch Upload Locations')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Batch Upload Locations</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <div class="alert alert-info" role="alert">
                        <h6>CSV Format Required:</h6>
                        <p class="mb-0 small">
                            code, description, is_active
                        </p>
                    </div>

                    <div class="mb-3">
                        <a href="{{ asset('samples/locations_sample.csv') }}" class="btn btn-outline-success" download>
                            <i class="fas fa-download"></i> Download Sample CSV Template
                        </a>
                    </div>

                    <form method="POST" action="{{ route('inventory.location-batch-store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="csv_file" class="form-label">Select CSV File</label>
                            <input
                                type="file"
                                class="form-control @error('csv_file') is-invalid @enderror"
                                id="csv_file"
                                name="csv_file"
                                accept=".csv,.txt"
                                required
                            >
                            @error('csv_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Supported formats: CSV, TXT</small>
                        </div>

                        <div class="alert alert-warning">
                            <strong>Note:</strong> Rows with a duplicate code or missing code/description will be skipped. <code>is_active</code> defaults to Active if omitted.
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                            <a href="{{ route('inventory.locations') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Sample CSV Format</h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded"><small>code,description,is_active
CEBU,Cebu Branch,1
DAVAO,Davao Branch,1</small></pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
