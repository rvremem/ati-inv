@extends('layouts.app')

@section('title', 'Batch Upload Item Parts')

@section('content')
{{-- Up to three files, laid out the same way as the machines upload:
     a part and its stock are a parent/child pair, and flattening them into a
     single sheet means repeating every catalogue column on every stock row.
     Serialised parts take a serials file (one row per unit); non-serialised
     parts take a quantities file (one row per receipt). Both link to the items
     file by part number. --}}
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Batch Upload Item Parts, Serials &amp; Quantities</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <div class="alert alert-info" role="alert">
                        <h6>How the files link up</h6>
                        <p class="small mb-2">
                            <strong>item_parts.csv</strong> defines the catalogue. The other two receive stock
                            against it, and their <code>part_no</code> must match a part from the items file you
                            upload alongside them, or one already in the catalogue:
                        </p>
                        <ul class="small mb-2">
                            <li>
                                <strong>item_serials.csv</strong> &mdash; for <strong>serialised</strong> parts. One
                                row per physical unit, identified by <code>serial_no</code>.
                            </li>
                            <li>
                                <strong>item_quantities.csv</strong> &mdash; for <strong>non-serialised</strong>
                                parts. One row per receipt, with a <code>quantity</code> instead of a serial.
                            </li>
                        </ul>
                        <p class="small mb-0 text-muted">
                            Neither stock file contains a database id. Supplier, customer and hub are matched by
                            name. <strong>A <code>location</code> code that isn't on file is created for you</strong>
                            (active, in the row's hub). Every stock row receives stock into your hub exactly as the
                            Receive Parts screen would create it.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('inventory.item-batch-store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 mb-3">
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

                            <div class="col-md-4 mb-3">
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

                            <div class="col-md-4 mb-3">
                                <label for="quantities_file" class="form-label">Item Quantities CSV</label>
                                <input
                                    type="file"
                                    class="form-control @error('quantities_file') is-invalid @enderror"
                                    id="quantities_file"
                                    name="quantities_file"
                                    accept=".csv,.txt">
                                @error('quantities_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <a href="{{ route('inventory.item-quantities-csv') }}" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-download"></i> Download quantities template
                                </a>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <strong>Before you upload:</strong>
                            <ul class="mb-0 small mt-2">
                                <li>
                                    A <code>part_no</code> <strong>already in the catalogue is skipped</strong> &mdash;
                                    this upload only ever creates parts, it never edits one. Use Edit on the part
                                    itself to change something already on file. Its stock rows are still accepted:
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
                                    <strong>Serialised vs non-serialised decides which file.</strong> A serial against
                                    a part tracked by quantity is skipped, and a quantity against a serialised part is
                                    skipped too &mdash; put its units in the serials file, one row per serial. Stock
                                    Levels counts each kind its own way, so the wrong file would never show as the
                                    stock it claims to be.
                                </li>
                                <li>
                                    A serial <strong>already received is skipped</strong> &mdash; a serial is one
                                    physical unit, so importing it twice would double-count it. Quantities have no such
                                    check: receiving 10 more of a part is normal, so uploading the same quantities file
                                    twice <strong>adds the stock twice</strong>.
                                </li>
                                <li>
                                    The stock files carry the same fields as the <strong>Receive Parts</strong>
                                    screen. In the serials file <code>part_no</code>, <code>hub</code>,
                                    <code>serial_no</code> and <code>location</code> are required;
                                    in the quantities file it is <code>part_no</code>, <code>hub</code>,
                                    <code>quantity</code> and <code>location</code>.
                                    <code>quantity</code> must be a whole number greater than zero.
                                    A blank <code>date_received</code> falls back to today, and
                                    <code>ati_po</code>, <code>client</code>, <code>supplier</code> and
                                    <code>remarks</code> may be left empty.
                                    <br>
                                    <strong>The status is set by <code>ati_po</code>:</strong> a row with a PO is
                                    booked in as <code>Receiving</code>; a row without one is booked in as
                                    <code>Old Stock</code>. There is no status column.
                                </li>
                                <li>
                                    <code>hub</code>, <code>client</code> and <code>supplier</code> are matched by
                                    name. The <code>hub</code> must already be on file; a <code>client</code> or
                                    <code>supplier</code> that isn't is created for you (see below). The <code>location</code> is created if it isn't
                                    on file; if it is, it must belong to the row&rsquo;s <code>hub</code>, which is
                                    what the filtered dropdown enforces on screen.
                                </li>
                                <li>
                                    <strong>Watch location spellings:</strong> a typo such as <code>A-01</code>
                                    instead of <code>A01</code> creates a new location instead of failing the row.
                                    New ones are listed in the result message. A location that exists but is
                                    <strong>Inactive</strong> causes the row to be skipped.
                                </li>
                                <li>
                                    In every file, a <code>supplier</code> not already on file is
                                    <strong>created for you</strong> and set to Active, so an import never stalls on a
                                    supplier nobody has entered yet. In the stock files the same goes for a
                                    <code>client</code>. New ones are reported back with the result.
                                    <br>
                                    <strong>Watch the spelling:</strong> a misspelt name looks exactly like a new
                                    one, so <code>Local Suppier</code> becomes a new supplier rather than matching
                                    <code>Local Supplier</code>. Tidy any strays under Inventory &rarr; Suppliers
                                    and Settings &rarr; Customers.
                                    A supplier or client that is on file but <strong>Inactive</strong> is matched and
                                    used as it is &mdash; no duplicate is created, and its status is left alone.
                                </li>
                                <li>
                                    The stock files are optional. All files are processed together, so if anything
                                    fails none is saved. Skipped rows are reported back with the reason; the rest
                                    still import.
                                </li>
                                <li>
                                    The templates use <code>REPLACE-WITH-PART-NO-1</code> / <code>-2</code> and
                                    <code>REPLACE-WITH-SERIAL-1</code> / <code>-2</code>. Uploading them unedited
                                    creates junk parts and stock under those names.
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

                    <h6 class="mt-4 mb-2">Item serials file <span class="text-muted small">(serialised parts)</span></h6>
                    <pre class="bg-light p-3 rounded mb-0" style="overflow-x:auto;"><small>{{ $csvLine($serialColumns) }}
@foreach ($serialRows as $row){{ $csvLine($row) }}
@endforeach</small></pre>

                    <h6 class="mt-4 mb-2">Item quantities file <span class="text-muted small">(non-serialised parts)</span></h6>
                    <pre class="bg-light p-3 rounded mb-0" style="overflow-x:auto;"><small>{{ $csvLine($quantityColumns) }}
@foreach ($quantityRows as $row){{ $csvLine($row) }}
@endforeach</small></pre>
                    <p class="small text-muted mb-0 mt-3">
                        The quantities sample uses <code>REPLACE-WITH-PART-NO-2</code>, the non-serialised part in
                        the items sample, so the two line up when uploaded together.
                    </p>
                </div>
            </div>

            <x-back-link :href="route('inventory.items')">Back to Item Parts</x-back-link>
        </div>
    </div>
</div>
@endsection