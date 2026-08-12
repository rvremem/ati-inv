@extends('layouts.app')

@section('title', 'Outbound Report')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <h2 class="mb-4">Outbound Report</h2>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.outbound') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="outboundTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Out Date</th>
                        <th>MRIS No</th>
                        <th>Part No</th>
                        <th>Description</th>
                        <th>Serial No</th>
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
    $('#outboundTable').DataTable(reportTableOptions(
        '{{ route('reports.outbound-data') }}',
        @json(request()->only(['from_date', 'to_date', 'customer_id'])),
        [
            { data: 'out_date', render: esc },
            { data: 'mris_no', render: esc },
            { data: 'part_no', orderable: false, render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'description', orderable: false, render: esc },
            { data: 'serial_no', render: esc },
            { data: 'quantity', className: 'text-center', render: esc },
            { data: 'location', orderable: false, render: esc },
            { data: 'status', render: statusBadge }
        ],
        [[0, 'desc']]
    ));
});
</script>
@endsection
