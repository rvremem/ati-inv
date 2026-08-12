@extends('layouts.app')

@section('title', 'Batch Upload Machines')

@section('content')
{{-- Two files rather than one: a machine and its parts are a parent/child pair,
     and flattening them into a single sheet means repeating every machine column
     on every part row. They are uploaded together and linked by serial number. --}}
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Batch Upload Machines &amp; Parts</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <div class="alert alert-info" role="alert">
                        <h6>How the two files link up</h6>
                        <p class="small mb-2">
                            <strong>machines.csv</strong> defines the machines. <strong>machine_parts.csv</strong>
                            hangs parts off them: its <code>machine_serial_no</code> column must match a
                            <code>serial_no</code> from the machines file you upload alongside it.
                        </p>
                        <p class="small mb-0 text-muted">
                            Neither file contains a database id. Category, customer, supplier and location are
                            matched by their code or name, and <code>part_no</code> is matched against the parts
                            catalogue &mdash; <strong>a part number that isn't in the catalogue is added to it</strong>
                            using <code>part_description</code> and <code>part_type</code>, so a machine never
                            arrives missing half its parts.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('machines.batch-store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="machines_file" class="form-label">
                                    Machines CSV <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="file"
                                    class="form-control @error('machines_file') is-invalid @enderror"
                                    id="machines_file"
                                    name="machines_file"
                                    accept=".csv,.txt"
                                    required
                                >
                                @error('machines_file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <a href="{{ route('machines.machines-template') }}" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-download"></i> Download machines template
                                </a>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="parts_file" class="form-label">Machine Parts CSV</label>
                                <input
                                    type="file"
                                    class="form-control @error('parts_file') is-invalid @enderror"
                                    id="parts_file"
                                    name="parts_file"
                                    accept=".csv,.txt"
                                >
                                @error('parts_file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <a href="{{ route('machines.parts-template') }}" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-download"></i> Download parts template
                                </a>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <strong>Before you upload:</strong>
                            <ul class="mb-0 small mt-2">
                                <li>
                                    A <code>serial_no</code> <strong>already on file is skipped, and so are its
                                    parts</strong> &mdash; this upload only ever creates machines, it never edits
                                    one. Use Add Parts on the machine itself for machines already in the system.
                                </li>
                                <li>
                                    <code>serial_no</code>, <code>description</code>, <code>category</code> and
                                    <code>date_received</code> are required on every machine row. The category
                                    must already exist &mdash; unlike parts, categories are not created for you.
                                </li>
                                <li>
                                    The parts file is optional. Without it you get machines with no parts, which
                                    you can fill in later.
                                </li>
                                <li>
                                    Skipped rows are reported back with the reason. The rest still import.
                                </li>
                                <li>
                                    The templates use <code>REPLACE-WITH-SERIAL-1</code> / <code>-2</code> as
                                    serials. Uploading them unedited creates two junk machines under those names.
                                </li>
                            </ul>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                            <a href="{{ route('machines.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Rendered from the same rows the template downloads use, so what is
                 shown here and what lands in the file cannot drift apart. --}}
            @php
                $csvLine = fn ($values) => implode(',', array_map(
                    fn ($v) => str_contains((string) $v, ',') ? '"' . $v . '"' : $v,
                    $values
                ));
            @endphp

            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Sample &mdash; machines.csv</h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded mb-0" style="overflow-x:auto;"><small>{{ $csvLine($machineColumns) }}
@foreach ($machineRows as $row){{ $csvLine($row) }}
@endforeach</small></pre>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Sample &mdash; machine_parts.csv</h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded mb-0" style="overflow-x:auto;"><small>{{ $csvLine($partColumns) }}
@foreach ($partRows as $row){{ $csvLine($row) }}
@endforeach</small></pre>
                    <p class="small text-muted mb-0 mt-3">
                        Two rows share <code>REPLACE-WITH-SERIAL-1</code>: that is one machine with two parts.
                        The second of them names a part number that isn't in the catalogue, and shows what gets
                        created for it.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
