@extends('layouts.app')

@section('title', 'Batch Upload Maintenance')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Batch Upload Maintenance Contracts</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    {{-- Columns come from MaintenanceController::BATCH_COLUMNS so this
                         list and the downloadable template can never disagree. --}}
                    <div class="alert alert-info" role="alert">
                        <h6>CSV Format Required:</h6>
                        <p class="mb-2 small">
                            {{ implode(', ', $columns) }}
                        </p>
                        <p class="mb-0 small text-muted">
                            <strong>customer</strong> is matched against the customer name on record.
                            If <strong>end_date</strong> is omitted it defaults to 12 months after the start date.
                        </p>
                    </div>

                    <div class="mb-3">
                        <a href="{{ route('maintenance.sample-csv') }}" class="btn btn-outline-success">
                            <i class="fas fa-download"></i> Download CSV Template
                        </a>
                        <small class="form-text text-muted d-block mt-1">
                            Pre-filled with the correct headers and two example rows.
                        </small>
                    </div>

                    <form method="POST" action="{{ route('maintenance.batch-store') }}" enctype="multipart/form-data">
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
                            <strong>Note:</strong> This will import all records from the CSV file. Please verify the file contents before uploading.
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                            <a href="{{ route('maintenance.index') }}" class="btn btn-secondary">
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
                    {{-- Rendered from the same constants the template download uses. --}}
                    <pre class="bg-light p-3 rounded mb-0"><small>{{ implode(',', $columns) }}
@foreach ($sampleRows as $row){{ implode(',', $row) }}
@endforeach</small></pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
