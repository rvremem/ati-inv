@extends('layouts.app')

@section('title', 'Maintenance Contract Expiry Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="{{ route('maintenance.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Contracts
        </a>
        {{-- Exports every active contract, not the page on screen: the table
             below is paged 10 at a time. Same style as the Activity Log export. --}}
        <a href="{{ route('maintenance.report-csv') }}" class="btn btn-outline-primary">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <h2 class="mb-1">Maintenance Contract Expiry Report</h2>
    <!-- <p class="text-muted mb-4">
        Active contracts, soonest to expire first &middot; Generated {{ now()->format('M d, Y') }}
    </p> -->

    {{-- Counted in SQL across every active contract, not just the page on
         screen, so the totals stay right whatever the table is showing. --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-danger">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">Expired</div>
                    <div class="fs-4 fw-bold text-danger">{{ number_format($expiredCount) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">Expiring within 30 days</div>
                    <div class="fs-4 fw-bold text-warning">{{ number_format($soonCount) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">Not due yet</div>
                    <div class="fs-4 fw-bold text-success">{{ number_format($okCount) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="expiryTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Equipment</th>
                        <th>Serial No</th>
                        <th>PO No</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days Left</th>
                        <th style="width:130px;">Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

// DataTables calls render as (data, type, row) — the second argument is the
// draw type, not the row. Both of these read the column's own value, which is
// what `data` already holds.
const daysCell = (data) => {
    if (data === null || data === undefined) return '-';
    return data < 0
        ? Math.abs(data) + ' day(s) ago'
        : data + ' day(s)';
};

const stateBadge = (data) => ({
    expired: '<span class="badge bg-danger">Expired</span>',
    soon:    '<span class="badge bg-warning text-dark">Expiring Soon</span>',
    ok:      '<span class="badge bg-success">Active</span>',
}[data] || '<span class="badge bg-secondary">—</span>');

$(function () {
    $('#expiryTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: '{{ route('maintenance.report-data') }}',
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[5, 'asc']],
        pagingType: 'simple',
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
        language: {
            search: '', searchPlaceholder: 'Search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            processing: 'Loading...',
            emptyTable: 'No active maintenance contracts.',
            paginate: { previous: '← Prev', next: 'Next →' }
        },
        // Tints the whole row to match its status badge, as the old full-page
        // report did.
        createdRow: (tr, data) => {
            if (data.state === 'expired') tr.classList.add('table-danger');
            else if (data.state === 'soon') tr.classList.add('table-warning');
        },
        columns: [
            { data: 'client', render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'equipment', render: esc },
            { data: 'serial_no', render: esc },
            { data: 'po_no', render: esc },
            { data: 'start_date', className: 'text-nowrap', render: esc },
            { data: 'end_date', className: 'text-nowrap', render: esc },
            { data: 'days_left', className: 'text-nowrap', render: daysCell },
            { data: 'state', orderable: false, searchable: false, render: stateBadge }
        ]
    });
});
</script>
@endsection
