@extends('layouts.app')

@section('title', 'Receiving Report')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <h2 class="mb-4">Receiving Report</h2>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.receiving') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">-- All --</option>
                        <option value="Receiving" {{ request('status') == 'Receiving' ? 'selected' : '' }}>Receiving</option>
                        <option value="Return" {{ request('status') == 'Return' ? 'selected' : '' }}>Return</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            {{-- no-datatable keeps the layout's auto-init off; initialised below
                 in server-side mode instead. --}}
            <table id="receivingTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>PO No</th>
                        <th>Part No</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@include('partials.datatable-helpers')
<script>
$(function () {
    $('#receivingTable').DataTable(reportTableOptions(
        '{{ route('reports.receiving-data') }}',
        @json(request()->only(['from_date', 'to_date', 'status'])),
        [
            { data: 'date_received' , render: esc },
            { data: 'ati_po', render: esc },
            { data: 'part_no', orderable: false, render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'description', orderable: false, render: esc },
            { data: 'quantity', className: 'text-center', render: esc },
            { data: 'location', orderable: false, render: esc },
            { data: 'status', render: statusBadge }
        ],
        [[0, 'desc']]
    ));
});
</script>
@endsection
