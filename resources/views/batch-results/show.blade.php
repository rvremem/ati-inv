@extends('layouts.app')

@section('title', 'Upload Results')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <h2 class="mb-1">Upload Results &mdash; {{ $result['title'] }}</h2>
            <div class="text-muted small">Uploaded {{ \Carbon\Carbon::parse($result['created_at'])->format('M j, Y g:i A') }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ $result['back_url'] }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="{{ route('batch-results.skipped-csv', $result['id']) }}" class="btn btn-primary">
                <i class="fas fa-file-download"></i> Download skipped rows (CSV)
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($result['counts'] as $label => $count)
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-3 fw-bold {{ $label === 'Skipped' && $count > 0 ? 'text-danger' : '' }}">
                            {{ number_format($count) }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="alert alert-info small">
        <strong>To fix the skipped rows:</strong> download them, correct them in Excel and upload the
        file again. Each row's <code>upload_error</code> column says what was wrong; it is ignored on upload,
        so there is no need to delete it. Rows that already went in are not touched.
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>Why rows were skipped</strong></div>
                {{-- Clicking a reason filters the table to just those rows. --}}
                <div class="list-group list-group-flush" id="reasonGroups">
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between active" data-group="">
                        <span>All skipped rows</span>
                        <span class="badge bg-secondary rounded-pill">{{ number_format(count($result['skipped'])) }}</span>
                    </button>
                    @foreach ($groups as $group => $count)
                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between gap-2 text-start" data-group="{{ $group }}">
                            <span>{{ $group }}</span>
                            <span class="badge bg-danger rounded-pill align-self-start">{{ number_format($count) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    {{-- no-datatable: initialised below from JSON rather than by the
                         layout, so thousands of rows are not written out as HTML. --}}
                    <table id="skippedTable" class="table no-datatable mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th>Line</th>
                                <th>Identifier</th>
                                <th>Reason</th>
                                <th>Group</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function () {
    const rows = {!! json_encode(array_map(fn ($s) => [
        $s['line'], $s['label'], $s['reason'], $s['group'],
    ], $result['skipped']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) !!};

    const table = $('#skippedTable').DataTable({
        data: rows,
        deferRender: true,
        responsive: true,
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
        order: [[0, 'asc']],
        pagingType: 'simple',
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
        language: {
            search: '',
            searchPlaceholder: 'Search PO, reason...',
            lengthMenu: 'Show _MENU_ rows',
            info: 'Showing _START_ to _END_ of _TOTAL_ skipped rows',
            paginate: { previous: '← Prev', next: 'Next →' }
        },
        columnDefs: [
            { targets: 0, width: '70px' },
            { targets: 3, visible: false },
        ],
    });

    $('#reasonGroups').on('click', '[data-group]', function () {
        $('#reasonGroups [data-group]').removeClass('active');
        $(this).addClass('active');

        const group = $(this).data('group');
        // Exact match on the hidden group column.
        table.column(3).search(group ? '^' + $.fn.dataTable.util.escapeRegex(group) + '$' : '', true, false).draw();
    });
});
</script>
@endsection
