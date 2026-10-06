@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Activity Log</h2>
            <small class="text-muted">
                @if ($isDefaultRange)
                    Showing the last {{ App\Http\Controllers\ReportController::ACTIVITY_LOG_DAYS }} days
                    &mdash; filter by date to go further back
                @else
                    {{ $from->format('M d, Y') }} to {{ $to->format('M d, Y') }}
                @endif
            </small>
        </div>
        {{-- Export is Super Admin / Developer / Admin only; the controller
             enforces it as well, since the URL can be typed. Exports whatever
             the filters currently select, not just the page on screen — the
             same range resolver feeds the table and the download. --}}
        @if ($canExport)
            <a href="{{ route('reports.activity-log-csv', request()->only('from_date', 'to_date')) }}"
               class="btn btn-outline-primary">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.activity-log') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                </div>
                <x-hub-filter />
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <a href="{{ route('reports.activity-log') }}" class="btn btn-secondary w-100">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            {{-- Server-side: clearing the dates can select all 75k sys_log
                 rows, so search, sort and paging are all done in SQL and only
                 the current page is sent.

                 Details carries the remarks. They used to be export-only, which
                 meant an entry read "Added New Item" with no way to tell which
                 item — the detail is the point of the log, so it belongs on
                 screen as well as in the CSV. --}}
            <table id="activityLogTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width: 180px;">Date &amp; Time</th>
                        <th style="width: 160px;">User</th>
                        <th style="width: 100px;">Role</th>
                        <th style="width: 180px;">Action</th>
                        <th>Details</th>
                        <th style="width: 130px;">IP Address</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    <x-back-link :href="route('reports.index')" />
</div>
@endsection

@section('scripts')
<script>
const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

const roleBadge = (role) => {
    const cls = (role === 'SA' || role === 'Developer') ? 'bg-danger'
              : role === 'Admin' ? 'bg-primary'
              : role === 'Engineer' ? 'bg-warning text-dark'
              : 'bg-secondary';
    return '<span class="badge ' + cls + '">' + esc(role) + '</span>';
};

$(function () {
    $('#activityLogTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: '{{ route('reports.activity-log-data') }}',
            // Carry the date filters through so the table matches the header
            // and the CSV export.
            data: d => {
                d.from_date = @json(request('from_date'));
                d.to_date = @json(request('to_date'));
                d.hub_id = @json(request('hub_id'));
            }
        },
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[0, 'desc']],
        pagingType: 'simple',
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
        language: {
            search: '', searchPlaceholder: 'Search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            processing: 'Loading...',
            emptyTable: 'No activity logged in this date range.',
            zeroRecords: 'No entries match your search.',
            paginate: { previous: '← Prev', next: 'Next →' }
        },
        columns: [
            { data: 'date', className: 'text-nowrap', render: esc },
            { data: 'user', render: esc },
            { data: 'role', render: roleBadge },
            { data: 'action', className: 'text-nowrap', render: esc },
            // Not orderable: remarks are free text, so sorting them is noise.
            { data: 'remarks', orderable: false, render: (d) => d
                ? '<span class="text-muted small">' + esc(d) + '</span>'
                : '<span class="text-muted">—</span>' },
            // Monospaced so addresses line up column-wise and are easy to
            // compare down the page. Not orderable for the same reason as
            // remarks: the server only sorts the four indexed columns.
            { data: 'ip_address', orderable: false, className: 'text-nowrap', render: (d) =>
                (d && d !== '—')
                    ? '<code class="small text-muted">' + esc(d) + '</code>'
                    : '<span class="text-muted">—</span>' }
        ]
    });
});
</script>
@endsection
