@extends('layouts.app')

@section('title', 'Batch Upload Customers')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Batch Upload Customers</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <div class="alert alert-info" role="alert">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6>CSV Format Required:</h6>
                                <p class="mb-2 small">{{ implode(', ', $columns) }}</p>
                                <p class="mb-0 small text-muted">
                                    One row per customer. <strong>name</strong> is required;
                                    <strong>is_active</strong> may be left blank and defaults to Active. A file
                                    containing only a <code>name</code> column works &mdash; only the header names
                                    are read.
                                </p>
                            </div>
                            <a href="{{ route('settings.customer-sample-csv') }}" class="btn btn-sm btn-outline-primary flex-shrink-0 ms-3">
                                <i class="fas fa-download"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('settings.customer-batch-store') }}" enctype="multipart/form-data">
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
                                    A <code>name</code> <strong>already on file is skipped</strong> &mdash; this
                                    upload only ever creates customers, it never edits one. Use Edit on the
                                    customer itself to change something already in the system.
                                </li>
                                <li>
                                    <code>is_active</code> accepts <code>1</code>/<code>0</code>,
                                    <code>true</code>/<code>false</code> or <code>yes</code>/<code>no</code>.
                                </li>
                                <li>
                                    Rows with a blank name, a name listed twice, or the wrong number of columns are
                                    skipped and reported back with the reason. The rest still import.
                                </li>
                                <li>
                                    The template uses <code>REPLACE-WITH-CUSTOMER-1</code> / <code>-2</code> as
                                    names. Uploading it unedited creates two junk customers under those names.
                                </li>
                            </ul>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                            <a href="{{ route('settings.customers') }}" class="btn btn-secondary">
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
