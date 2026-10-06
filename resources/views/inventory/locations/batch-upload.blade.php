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
                        <p class="mb-2 small">{{ implode(', ', $columns) }}</p>
                        <p class="mb-0 small text-muted">
                            <strong>hub</strong> is matched by name against the Hubs list and is
                            <strong>required</strong> &mdash; receiving offers locations by hub, so one
                            loaded without a hub would exist but never appear in a dropdown.
                            <strong>is_active</strong> defaults to Active if omitted.
                        </p>
                    </div>

                    <div class="mb-3">
                        <a href="{{ route('inventory.location-sample-csv') }}" class="btn btn-outline-success">
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
                            <strong>Note:</strong> A row is skipped and reported back with the reason when its
                            <code>code</code> is already on file or listed twice, when <code>code</code> or
                            <code>description</code> is missing, or when its <code>hub</code> is missing or not
                            on file. The rest still import. Unlike suppliers, a hub that isn't on file is
                            <strong>not</strong> created for you &mdash; hubs are a fixed list, so an unknown
                            name is a typo rather than a new branch.
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
