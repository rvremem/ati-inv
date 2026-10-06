@extends('layouts.app')

@section('title', 'Batch Upload Item Parts')

@section('content')
{{-- Two files rather than one, laid out the same way as the machines upload:
     a part and its serials are a parent/child pair, and flattening them into a
     single sheet means repeating every catalogue column on every serial row.
     They are uploaded together and linked by part number. --}}
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Batch Upload Item Parts &amp; Serials</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <div class="alert alert-info" role="alert">
                        <h6>How the two files link up</h6>
                        <p class="small mb-2">
                            <strong>item_parts.csv</strong> defines the catalogue. <strong>item_serials.csv</strong>
                            hangs serials off them: its <code>part_no</code> column must match a
                            <code>part_no</code> from the items file you upload alongside it, or one already in
                            the catalogue.
                        </p>
                        <p class="small mb-0 text-muted">
                            Neither file contains a database id. Supplier, location and customer are matched by
                            their code or name. A serial is not a catalogue field &mdash; it exists because a unit
                            arrived &mdash; so <strong>each serial row receives one unit into stock</strong>, filed
                            against your hub, exactly as the Receive Parts screen would create it.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('inventory.item-batch-store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="csv_file" class="form-label">
                                    Item Parts CSV
                                </label>
                                <input
                                    type="file"
                                    class="form-control @error('csv_file') is-invalid @enderror"
                                    id="csv_file"
                                    name="csv_file"
                                    accept=".csv,.txt">
                                @error('csv_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <a href="{{ route('inventory.item-sample-csv') }}" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-download"></i> Download items template
                                </a>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="serials_file" class="form-label">Item Serials CSV</label>
                                <input
                                    type="file"
                                    class="form-control @error('serials_file') is-invalid @enderror"
                                    id="serials_file"
                                    name="serials_file"
                                    accept=".csv,.txt">
                                @error('serials_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <a href="{{ route('inventory.item-serials-csv') }}" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-download"></i> Download serials template
                                </a>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <strong>Before you upload:</strong>
                            <ul class="mb-0 small mt-2">
                                <li>
                                    A <code>part_no</code> <strong>already in the catalogue is skipped</strong> &mdash;
                                    this upload only ever creates parts, it never edits one. Use Edit on the part
                                    itself to change something already on file. Its serials are still accepted:
                                    unlike machines, an existing part has an id to attach them to.
                                </li>
                                <li>
                                    <code>part_no</code>, <code>description</code> and <code>type</code> are required
                                    on every item row; everything else takes its default.
                                    <code>unit_cost</code> and <code>min_stock</code> default to <code>0</code>,
                                    <code>is_serialized</code> to off and <code>is_active</code> to Active.
                                    <code>is_serialized</code> and <code>is_active</code> accept
                                    <code>1</code>/<code>0</code>, <code>true</code>/<code>false</code> or
                                    <code>yes</code>/<code>no</code>.
                                </li>
                                <li>
                                    Only <strong>serialised</strong> parts accept serials. A serial against a part
                                    tracked by quantity is skipped &mdash; Stock Levels counts those by quantity, so
                                    the serial would never show as the unit it claims to be.
                                </li>
                                <li>
                                    A serial <strong>already received is skipped</strong> &mdash; a serial is one
                                    physical unit, so importing it twice would double-count it.
                                </li>
                                <li>
                                    The serials file carries the same fields as the <strong>Receive Parts</strong>
                                    screen. <code>part_no</code>, <code>hub</code>, <code>serial_no</code> and
                                    <code>location</code> are required; a blank <code>date_received</code> falls
                                    back to today, and <code>ati_po</code>, <code>client</code>,
                                    <code>supplier</code> and <code>remarks</code> may be left empty.
                                </li>
                                <li>
                                    <code>hub</code>, <code>client</code> and <code>supplier</code> are matched by
                                    name and must already be on file &mdash; unlike the items file, nothing here is
                                    created for you. The <code>location</code> must belong to the row&rsquo;s
                                    <code>hub</code>, which is what the filtered dropdown enforces on screen.
                                </li>
                                <li>
                                    A <code>supplier</code> not already on file is <strong>created for you</strong>
                                    and set to Active, so an import never stalls on a supplier nobody has entered
                                    yet. The count is reported back with the result.
                                    <br>
                                    <strong>Watch the spelling:</strong> a misspelt name looks exactly like a new
                                    one, so <code>Local Suppier</code> becomes a new supplier rather than matching
                                    <code>Local Supplier</code>. Tidy any strays under Inventory &rarr; Suppliers.
                                </li>
                                <li>
                                    A supplier that is on file but <strong>Inactive</strong> is matched and used as
                                    it is &mdash; no duplicate is created, and its status is left alone.
                                </li>
                                <li>
                                    The serials file is optional. Both files are processed together, so if anything
                                    fails neither is saved. Skipped rows are reported back with the reason; the rest
                                    still import.
                                </li>
                                <li>
                                    The templates use <code>REPLACE-WITH-PART-NO-1</code> / <code>-2</code> and
                                    <code>REPLACE-WITH-SERIAL-1</code> / <code>-2</code>. Uploading them unedited
                                    creates junk parts under those names.
                                </li>
                            </ul>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                            <a href="{{ route('inventory.items') }}" class="btn btn-secondary">
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
                    {{-- Rendered from the same rows the template downloads use, so what
                         is shown here and what lands in the file cannot drift apart. --}}
                    @php
                    $csvLine = fn ($values) => implode(',', array_map(
                    fn ($v) => str_contains((string) $v, ',') ? '"' . $v . '"' : $v,
                    $values
                    ));
                    @endphp

                    <h6 class="mb-2">Item parts file</h6>
                    <pre class="bg-light p-3 rounded mb-0" style="overflow-x:auto;"><small>{{ $csvLine($columns) }}
@foreach ($sampleRows as $row){{ $csvLine($row) }}
@endforeach</small></pre>

                    <h6 class="mt-4 mb-2">Item serials file</h6>
                    <pre class="bg-light p-3 rounded mb-0" style="overflow-x:auto;"><small>{{ $csvLine($serialColumns) }}
@foreach ($serialRows as $row){{ $csvLine($row) }}
@endforeach</small></pre>
                </div>
            </div>

            <x-back-link :href="route('inventory.items')">Back to Item Parts</x-back-link>
        </div>
    </div>
</div>
@endsection