@extends('layouts.app')

@section('title', 'Batch Upload Item Parts')

@section('content')
{{-- Up to two files, laid out the same way as the machines upload:
     a part and its stock are a parent/child pair, and flattening them into a
     single sheet means repeating every catalogue column on every stock row.
     One stock file covers both kinds of part; the part's own is_serialized
     flag decides whether a row is read by serial or by quantity. --}}
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Batch Upload Item Parts &amp; Stock</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <div class="alert alert-info" role="alert">
                        <h6>How the files link up</h6>
                        <p class="small mb-2">
                            <strong>item_parts.csv</strong> defines the catalogue. <strong>item_stock.csv</strong>
                            receives stock against it, and its <code>part_no</code> must match a part from the items
                            file you upload alongside it, or one already in the catalogue. Whether a row is read by
                            serial or by quantity depends on the part:
                        </p>
                        <ul class="small mb-2">
                            <li>
                                <strong>Serialised</strong> part &mdash; one row per physical unit, identified by
                                <code>serial_no</code>. Quantity is always <code>1</code>; the column is ignored.
                                A unit with no serial takes <code>N/A</code> and is saved as <code>N/A-1</code>,
                                <code>N/A-2</code>, <code>N/A-3</code>&hellip;
                            </li>
                            <li>
                                <strong>Non-serialised</strong> part &mdash; leave <code>serial_no</code> blank.
                                A blank <code>quantity</code> counts as <code>1</code>. Rows for the same part are
                                <strong>added together</strong> into one quantity: two rows of <code>part01</code>
                                save as <code>part01</code> qty <code>2</code>.
                            </li>
                        </ul>
                        <p class="small mb-0 text-muted">
                            The stock file contains no database ids. Client and hub are matched by name. <strong>A <code>location</code> code that isn't on file is created for you</strong>
                            (active, in the row's hub). Every stock row receives stock into your hub exactly as the
                            Receive Parts screen would create it.
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
                                <label for="stock_file" class="form-label">Item Stock CSV</label>
                                <input
                                    type="file"
                                    class="form-control @error('stock_file') is-invalid @enderror"
                                    id="stock_file"
                                    name="stock_file"
                                    accept=".csv,.txt">
                                @error('stock_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <a href="{{ route('inventory.item-stock-csv') }}" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-download"></i> Download stock template
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
                                    <strong>Serialised parts:</strong> <code>serial_no</code> is required &mdash; use
                                    <code>N/A</code> for a unit without one. A serial <strong>already received is
                                    skipped</strong>, and so is one listed twice in the file: a serial is one physical
                                    unit, so importing it twice would double-count it. <code>N/A</code> units are
                                    numbered after the highest <code>N/A-</code> already on file, so they never clash.
                                </li>
                                <li>
                                    <strong>Non-serialised parts:</strong> <code>serial_no</code> must be blank (or
                                    <code>N/A</code>). <code>quantity</code> must be a whole number greater than
                                    zero; left blank it counts as <code>1</code>. Rows are added together when they share the same part,
                                    <code>hub</code>, <code>location</code>, <code>ati_po</code> and
                                    <code>client</code>; rows that differ in any of those are saved separately. There is no duplicate check, so
                                    uploading the same file twice <strong>adds the stock twice</strong>.
                                </li>
                                <li>
                                    <strong>Required on every stock row:</strong> <code>part_no</code>,
                                    <code>hub</code>, <code>location</code>, and <code>serial_no</code> for a
                                    serialised part. <code>ati_po</code> and <code>remarks</code> may
                                    be left empty. <strong>A blank <code>client</code> is saved as
                                    <code>Automated Technologies Inc.</code></strong>
                                    <br>
                                    There is no date or supplier column: <strong>date received is today</strong>, and
                                    the <strong>supplier is the one set on the part</strong> in Item Parts.
                                    <br>
                                    <strong>The status is set by <code>ati_po</code>:</strong> a row with a PO is
                                    booked in as <code>Receiving</code>; a row without one is booked in as
                                    <code>Old Stock</code>. There is no status column.
                                </li>
                                <li>
                                    <code>hub</code> and <code>client</code> are matched by name. The
                                    <code>hub</code> must already be on file; a <code>client</code> that isn't is
                                    created for you (see below). The <code>location</code> is created if it isn't
                                    on file; if it is, it must belong to the row&rsquo;s <code>hub</code>, which is
                                    what the filtered dropdown enforces on screen.
                                </li>
                                <li>
                                    <strong>Same shelf code at another hub:</strong> location codes are unique across
                                    all hubs, so when a code such as <code>R2AL1</code> already belongs to another hub,
                                    the row's hub is put in front &mdash; <code>R2AL1</code> at Cebu becomes
                                    <code>CEBU-R2AL1</code>.
                                </li>
                                <li>
                                    <strong>Serials on a non-serialised part:</strong> if the file gives a real serial
                                    for a part that Item Parts marks as not serialised, the part is
                                    <strong>switched to serialised</strong> and all of its rows are read as serial
                                    units. Its rows without a serial then need <code>N/A</code>. Switched parts are
                                    listed in the result message.
                                </li>
                                <li>
                                    <strong>Several clients in one cell</strong> (e.g.
                                    <code>Araneta Center Inc.,CTBC Bank Corp.</code>): a receiving row has one client,
                                    so the row is saved to <code>Automated Technologies Inc.</code> and the list is kept
                                    in remarks as <code>Clients: &hellip;</code>.
                                </li>
                                <li>
                                    <strong>Watch location spellings:</strong> a typo such as <code>A-01</code>
                                    instead of <code>A01</code> creates a new location instead of failing the row.
                                    New ones are listed in the result message. A location that exists but is
                                    <strong>Inactive</strong> causes the row to be skipped.
                                </li>
                                <li>
                                    In the items file, a <code>supplier</code> not already on file is
                                    <strong>created for you</strong> and set to Active, so an import never stalls on a
                                    supplier nobody has entered yet. In the stock file the same goes for a
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
                                    The stock file is optional. Both files are processed together, so if anything
                                    fails neither is saved. Skipped rows are reported back with the reason; the rest
                                    still import.
                                </li>
                                <li>
                                    The templates use <code>REPLACE-WITH-PART-NO-1</code> / <code>-2</code> and
                                    <code>REPLACE-WITH-SERIAL-1</code>. Uploading them unedited creates junk parts and
                                    stock under those names.
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

                    <h6 class="mt-4 mb-2">Item stock file</h6>
                    <pre class="bg-light p-3 rounded mb-0" style="overflow-x:auto;"><small>{{ $csvLine($stockColumns) }}
@foreach ($stockRows as $row){{ $csvLine($row) }}
@endforeach</small></pre>
                    <p class="small text-muted mb-0 mt-3">
                        <code>REPLACE-WITH-PART-NO-1</code> is the serialised part in the items sample: its
                        <code>N/A</code> row is saved as <code>N/A-1</code> (or the next free number).
                        <code>REPLACE-WITH-PART-NO-2</code> is the non-serialised one: its two rows are added together
                        into one receipt of 15.
                    </p>
                </div>
            </div>

            <x-back-link :href="route('inventory.items')">Back to Item Parts</x-back-link>
        </div>
    </div>
</div>
@endsection
