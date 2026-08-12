@extends('layouts.app')

@section('title', 'Batch Move Parts')

@section('content')
{{-- Deliberately not the shared partials.batch-upload-form: every other batch
     upload inserts new records and skips anything already on file, and the
     shared page says so in as many words. This one edits existing stock, so it
     needs its own warnings. --}}
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Batch Move Parts</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    @php $columns = App\Http\Controllers\InventoryController::PARTS_LOCATION_BATCH_COLUMNS; @endphp

                    <div class="alert alert-info" role="alert">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6>CSV Format Required:</h6>
                                <p class="mb-2 small">{{ implode(', ', $columns) }}</p>
                                <p class="mb-0 small text-muted">
                                    One row per move. <strong>part_no</strong> and <strong>old_location</strong>
                                    must match a row on the Parts Location list — that is the availability check:
                                    a part only appears there while it still has stock on hand, so parts that are
                                    out cannot be moved. <strong>new_location</strong> must be an active location code.
                                    The whole on-hand quantity for that part at that location moves; issued units
                                    stay where they were booked.
                                </p>
                            </div>
                            <a href="{{ route('inventory.parts-location-sample-csv') }}" class="btn btn-sm btn-outline-primary flex-shrink-0 ms-3">
                                <i class="fas fa-download"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('inventory.parts-location-batch-store') }}" enctype="multipart/form-data">
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
                            <strong>Note:</strong> this upload <strong>changes existing records</strong> — it is
                            the only one that does. Rows naming an unknown part, a location with nothing on hand,
                            an inactive target location, or with the wrong number of columns are skipped and
                            reported back with the reason. The downloaded template has
                            <code>NEW-LOCATION-CODE</code> in the new_location column so that uploading it
                            unedited moves nothing.
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                            <a href="{{ route('inventory.parts-location') }}" class="btn btn-secondary">
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
                    {{-- Rendered from the same rows the template download uses. --}}
                    <pre class="bg-light p-3 rounded mb-0" style="overflow-x:auto;"><small>{{ implode(',', $columns) }}
@foreach ($sampleRows as $row){{ implode(',', array_map(fn ($v) => str_contains((string) $v, ',') ? '"' . $v . '"' : $v, $row)) }}
@endforeach</small></pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
