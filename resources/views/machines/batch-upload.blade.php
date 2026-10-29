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
                            <code>serial_no</code> from the machines file you upload alongside it, or a machine
                            already in the system. Upload either file on its own, or both together.
                        </p>
                        <p class="small mb-0 text-muted">
                            Neither file contains a database id. Category and supplier are matched by their
                            code or name. <strong>A <code>category</code>, <code>supplier</code> or
                            <code>customer</code> that isn't on file is added as a new active one</strong>,
                            and <strong>a <code>location</code> code that
                            isn't on file is added as a new active location</strong> (in the hub named in the
                            optional <code>hub</code> column, or <strong>Manila</strong> if left blank; your own
                            hub if your account is limited to one). <code>part_no</code> is matched against the parts catalogue &mdash;
                            <strong>a part number that isn't in the catalogue is added to it</strong> using
                            <code>part_description</code> and <code>part_type</code>, so a machine never
                            arrives missing half its parts.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('machines.batch-store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="machines_file" class="form-label">
                                    Machines CSV
                                </label>
                                <input
                                    type="file"
                                    class="form-control @error('machines_file') is-invalid @enderror"
                                    id="machines_file"
                                    name="machines_file"
                                    accept=".csv,.txt"
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
                                    A machine <code>serial_no</code> <strong>already on file is skipped</strong>
                                    &mdash; this upload only ever creates machines, it never edits one.
                                </li>
                                <li>
                                    <strong>Parts go on any machine that exists</strong> &mdash; one in this
                                    upload's machines file, or one already in the system. A part row naming a
                                    serial that is in neither is skipped. A part <code>serial_no</code> already
                                    installed on that machine is skipped too. <strong>A part without a serial that
                                    is already on the machine has the new <code>quantity</code> added to it</strong>
                                    &mdash; so uploading the same parts file twice doubles those quantities.
                                </li>
                                <li>
                                    <code>serial_no</code>, <code>description</code> and <code>category</code> are
                                    required on every machine row; a blank <code>date_received</code> falls back to
                                    today. Categories, suppliers, customers, locations and parts are created for you
                                    when not on file &mdash; <strong>watch the spelling</strong>, as a typo becomes a
                                    new entry. New categories get their code as the description; edit them in
                                    Settings &rarr; Categories. New ones are listed in the result message.
                                </li>
                                <li>
                                    <strong>Check customer and location spellings.</strong> A typo in
                                    <code>customer</code> or <code>location</code> (for example
                                    <code>A-01</code> instead of <code>A01</code>) creates a new customer or
                                    location instead of failing the row. New ones are listed in the result
                                    message so you can spot and fix them. A customer or location that exists
                                    but is inactive causes the row to be skipped.
                                </li>
                                <li>
                                    <strong>New locations need a hub.</strong> Put the hub's name in the
                                    <code>hub</code> column. Blank defaults to <strong>Manila</strong>, and an
                                    account limited to a single hub always uses its own. A hub name that isn't
                                    on file skips the row.
                                    New locations get their code as the description &mdash; edit them on the
                                    Locations page afterwards.
                                </li>
                                <li>
                                    Either file is optional, but upload at least one. Machines alone arrive with
                                    no parts, which a later parts-only upload can fill in. A parts row's new
                                    location is created in the hub of the machine it belongs to.
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
                    <p class="small text-muted mb-0 mt-3">
                        <code>hub</code> is optional (blank = Manila) and decides where a new
                        <code>location</code> is created. The machine is also filed under that hub.
                    </p>
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