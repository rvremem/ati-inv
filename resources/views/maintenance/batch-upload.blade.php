@extends('layouts.app')

@section('title', 'Batch Upload Maintenance Contracts')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Batch Upload Maintenance Contracts</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    {{-- Columns come from MaintenanceController::BATCH_COLUMNS so this
                         list and the downloadable template can never disagree. --}}
                    <div class="alert alert-info" role="alert">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6>CSV Format Required:</h6>
                                <p class="mb-2 small">{{ implode(', ', $columns) }}</p>
                                <p class="mb-0 small text-muted">
                                    One row per contract. <strong>customer</strong> is matched by name against
                                    the Customers list. Everything except <strong>end_date</strong> and
                                    <strong>remarks</strong> is required. Columns you don't need can be dropped
                                    from the file entirely &mdash; only the header names are read.
                                </p>
                            </div>
                            <a href="{{ route('maintenance.sample-csv') }}" class="btn btn-sm btn-outline-primary flex-shrink-0 ms-3">
                                <i class="fas fa-download"></i> Download Template
                            </a>
                        </div>
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
                            <strong>Before you upload:</strong>
                            <ul class="mb-0 small mt-2">
                                <li>
                                    A <code>customer</code> that doesn't match an <strong>active</strong> customer
                                    by name is skipped &mdash; unlike suppliers on the item import, customers are
                                    not created for you. Add them under Customers first.
                                </li>
                                <li>
                                    If <code>end_date</code> is blank it is calculated from
                                    <code>start_date</code> plus <code>coverage_months</code>, falling back to 12
                                    months when no coverage is given.
                                </li>
                                <li>
                                    Rows with a missing required field, an unmatched customer, an unreadable date,
                                    or the wrong number of columns are skipped and reported back with the reason.
                                    The rest still import.
                                </li>
                                <li>
                                    This upload only ever <strong>creates</strong> contracts &mdash; it never edits
                                    an existing one.
                                </li>
                            </ul>
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
                    {{-- Rendered from the same rows the template download uses, so what
                         is shown here and what lands in the file cannot drift apart. --}}
                    @php
                        $csvLine = fn ($values) => implode(',', array_map(
                            fn ($v) => str_contains((string) $v, ',') ? '"' . $v . '"' : $v,
                            $values
                        ));
                    @endphp
                    <pre class="bg-light p-3 rounded mb-0" style="overflow-x:auto;"><small>{{ $csvLine($columns) }}
@foreach ($sampleRows as $row){{ $csvLine($row) }}
@endforeach</small></pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
