{{-- Shared client-side helpers for the server-side DataTables report pages.
     statusBadge mirrors the palette in components/status-badge.blade.php, which
     can't be used here because rows are rendered in JavaScript. --}}
<script>
const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

const STATUS_COLOURS = {
    'Active':      ['#d1fae5', '#065f46'],
    'Received':    ['#d1fae5', '#065f46'],
    'Issued':      ['#d1fae5', '#065f46'],
    'Delivered':   ['#d1fae5', '#065f46'],
    'Available':   ['#d1fae5', '#065f46'],
    'Under Warranty': ['#d1fae5', '#065f46'],
    'In Stock':    ['#d1fae5', '#065f46'],
    'Receiving':   ['#dbeafe', '#1e40af'],
    'Approved':    ['#dbeafe', '#1e40af'],
    'Out':         ['#dbeafe', '#1e40af'],
    'Assembly':    ['#dbeafe', '#1e40af'],
    'Pending':     ['#fef3c7', '#92400e'],
    'Return':      ['#ffedd5', '#9a3412'],
    'Returned':    ['#ffedd5', '#9a3412'],
    'Cancelled':   ['#fee2e2', '#991b1b'],
    'Defective':   ['#fee2e2', '#991b1b'],
    'Unavailable': ['#fee2e2', '#991b1b'],
    'Expired':     ['#fee2e2', '#991b1b'],
    'Unknown':     ['#fef3c7', '#92400e'],
    'Inactive':    ['#f1f5f9', '#64748b'],
};

const statusBadge = (status) => {
    const [bg, colour] = STATUS_COLOURS[status] || ['#f1f5f9', '#475569'];
    return '<span style="background:' + bg + '; color:' + colour + '; font-size:0.72rem; font-weight:600;'
        + ' padding:3px 10px; border-radius:20px; display:inline-block; white-space:nowrap;">'
        + esc(status) + '</span>';
};

// Shared DataTables options for the report tables. Pass the ajax URL and the
// report's current filter values (baked in server-side at render time).
const reportTableOptions = (url, filters, columns, order) => ({
    serverSide: true,
    processing: true,
    ajax: { url: url, data: d => Object.assign(d, filters) },
    responsive: true,
    pageLength: 10,
    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
    order: order,
    pagingType: 'simple',
    dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
    language: {
        search: '',
        searchPlaceholder: 'Search...',
        lengthMenu: 'Show _MENU_ entries',
        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
        processing: 'Loading...',
        paginate: { previous: '← Prev', next: 'Next →' }
    },
    columns: columns
});
</script>
