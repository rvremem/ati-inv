<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Receiving;
use App\Models\PartRequest;
use App\Models\Machine;
use App\Models\DefectiveItem;
use App\Models\MaintenanceContract;
use App\Models\ItemPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * How far back the activity log opens by default. The date filters reach
     * further on request — this is a starting point, not a cap.
     */
    public const ACTIVITY_LOG_DAYS = 30;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('reports.index');
    }

    /**
     * Shared server-side DataTables responder for the report tables.
     *
     * $orderColumns is positional and maps a DataTables column index to a real
     * database column; use null for display-only columns (relations, computed
     * values) so they render but can't be ordered on. $searchCallback receives
     * the query and the search term so each report can decide how far into its
     * relations to search.
     *
     * recordsTotal is counted after the report's own date/customer filters but
     * before the search box, which is what DataTables expects.
     */
    private function dataTableJson(
        Request $request,
        $base,
        array $orderColumns,
        callable $searchCallback,
        string $defaultOrderColumn,
        string $defaultOrderDir,
        callable $mapper
    ) {
        $recordsTotal = (clone $base)->count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $searchCallback($base, $search);
        }

        $recordsFiltered = (clone $base)->count();

        $orderIdx = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        if ($orderIdx !== null && ($orderColumns[$orderIdx] ?? null) !== null) {
            $base->orderBy($orderColumns[$orderIdx], $orderDir);
        } else {
            $base->orderBy($defaultOrderColumn, $defaultOrderDir);
        }

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            // sqlsrv needs the ORDER BY above before OFFSET/FETCH is applied.
            $base->skip($start)->take($length);
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $base->get()->map($mapper),
        ]);
    }

    public function receivingReport()
    {
        // Rows load via server-side DataTables (see receivingData).
        return view('reports.receiving');
    }

    public function receivingData(Request $request)
    {
        // Part No / Description / Location come from relations - display only.
        $orderColumns = ['date_received', 'ati_po', null, null, 'quantity', null, 'status'];

        $base = Receiving::query()->with('itemPart', 'location');
        if ($request->filled('from_date')) {
            $base->whereDate('date_received', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $base->whereDate('date_received', '<=', $request->input('to_date'));
        }
        if ($request->filled('status')) {
            $base->where('status', $request->input('status'));
        }

        return $this->dataTableJson(
            $request,
            $base,
            $orderColumns,
            function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('ati_po', 'like', "%{$search}%")
                      ->orWhere('serial_no', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%")
                      ->orWhereHas('itemPart', fn ($i) => $i
                          ->where('part_no', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%"));
                });
            },
            'date_received',
            'desc',
            fn ($r) => [
                'date_received' => $r->date_received ? $r->date_received->format('M d, Y') : '-',
                'ati_po' => $r->ati_po ?? '-',
                'part_no' => $r->itemPart->part_no ?? '-',
                'description' => $r->itemPart->description ?? '—',
                'quantity' => $r->quantity,
                'location' => $r->location->code ?? '-',
                'status' => $r->status,
            ]
        );
    }

    public function outboundReport()
    {
        return view('reports.outbound');
    }

    public function outboundData(Request $request)
    {
        $orderColumns = ['out_date', 'mris_no', null, null, 'serial_no', 'quantity', null, 'status'];

        $base = PartRequest::query()->with('itemPart', 'location')->whereNotNull('out_date');
        if ($request->filled('from_date')) {
            $base->whereDate('out_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $base->whereDate('out_date', '<=', $request->input('to_date'));
        }
        if ($request->filled('customer_id')) {
            $base->where('customer_id', $request->input('customer_id'));
        }

        return $this->dataTableJson(
            $request,
            $base,
            $orderColumns,
            function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('mris_no', 'like', "%{$search}%")
                      ->orWhere('serial_no', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%")
                      ->orWhereHas('itemPart', fn ($i) => $i
                          ->where('part_no', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%"));
                });
            },
            'out_date',
            'desc',
            fn ($r) => [
                'out_date' => $r->out_date ? $r->out_date->format('M d, Y') : '-',
                'mris_no' => $r->mris_no ?? '-',
                'part_no' => $r->itemPart->part_no ?? '-',
                'description' => $r->itemPart->description ?? '—',
                'serial_no' => $r->serial_no ?? '-',
                'quantity' => $r->quantity,
                'location' => $r->location->code ?? '-',
                'status' => $r->status,
            ]
        );
    }

    public function defectiveReport()
    {
        return view('reports.defective');
    }

    public function defectiveData(Request $request)
    {
        $orderColumns = ['date_received', null, null, null, 'quantity', null, 'return_remarks'];

        $base = DefectiveItem::query()->with('itemPart', 'customer', 'engineer');
        if ($request->filled('from_date')) {
            $base->whereDate('date_received', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $base->whereDate('date_received', '<=', $request->input('to_date'));
        }

        return $this->dataTableJson(
            $request,
            $base,
            $orderColumns,
            function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('return_remarks', 'like', "%{$search}%")
                      ->orWhere('serial_no', 'like', "%{$search}%")
                      ->orWhereHas('itemPart', fn ($i) => $i
                          ->where('part_no', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%"))
                      ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            },
            'date_received',
            'desc',
            fn ($r) => [
                'date_received' => $r->date_received ? $r->date_received->format('M d, Y') : '-',
                'customer' => $r->customer->name ?? '-',
                'part_no' => $r->itemPart->part_no ?? '-',
                'description' => $r->itemPart->description ?? '—',
                'quantity' => $r->quantity,
                'engineer' => $r->engineer->name ?? '-',
                'remarks' => Str::limit($r->return_remarks, 50) ?: '-',
            ]
        );
    }

    public function stockReport(Request $request)
    {
        $asOf = $request->as_of_date ? $request->as_of_date . ' 23:59:59' : now()->toDateTimeString();

        $items = ItemPart::selectRaw("item_parts.id, item_parts.part_no, item_parts.description, item_parts.type, item_parts.min_stock, item_parts.unit_cost")
            ->get();

        $items->transform(function ($item) use ($asOf) {
            $item->receiving_qty = DB::table('receiving')
                ->where('item_part_id', $item->id)
                ->where('status', 'Receiving')
                ->where('created_at', '<=', $asOf)
                ->count();

            $item->return_qty = DB::table('receiving')
                ->where('item_part_id', $item->id)
                ->where('status', 'Return')
                ->where('created_at', '<=', $asOf)
                ->count();

            $item->out_qty = DB::table('part_requests')
                ->where('item_part_id', $item->id)
                ->where('status', 'Out')
                ->where('created_at', '<=', $asOf)
                ->count();

            $item->on_hand = ($item->receiving_qty + $item->return_qty) - $item->out_qty;

            return $item;
        });

        return view('reports.stock', compact('items', 'asOf'));
    }

    public function machineReport()
    {
        return view('reports.machines');
    }

    public function machineData(Request $request)
    {
        // Category / Customer / Location are relations - display only.
        $orderColumns = ['date_received', 'serial_no', 'description', 'model', null, null, null, 'status'];

        $base = Machine::query()->with('category', 'customer', 'location');
        if ($request->filled('as_of_date')) {
            $base->whereDate('date_received', '<=', $request->input('as_of_date'));
        }

        return $this->dataTableJson(
            $request,
            $base,
            $orderColumns,
            function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('serial_no', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%")
                      ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            },
            'date_received',
            'desc',
            fn ($m) => [
                'date_received' => $m->date_received ? $m->date_received->format('M d, Y') : '-',
                'serial_no' => $m->serial_no,
                'description' => $m->description,
                'model' => $m->model ?? '-',
                'category' => $m->category->name ?? '-',
                'customer' => $m->customer->name ?? '-',
                'location' => $m->location->code ?? '-',
                'status' => $m->status,
            ]
        );
    }

    public function maintenanceReport()
    {
        return view('reports.maintenance');
    }

    public function maintenanceData(Request $request)
    {
        $orderColumns = [null, 'po_no', 'serial_no', 'description', 'brand', 'coverage_months', 'start_date', 'end_date', 'is_active'];

        $base = MaintenanceContract::query()->with('customer')->where('is_active', true);
        if ($request->filled('customer_id')) {
            $base->where('customer_id', $request->input('customer_id'));
        }

        return $this->dataTableJson(
            $request,
            $base,
            $orderColumns,
            function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('po_no', 'like', "%{$search}%")
                      ->orWhere('serial_no', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('brand', 'like', "%{$search}%")
                      ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            },
            'end_date',
            'asc',
            fn ($c) => [
                'customer' => $c->customer->name ?? '-',
                'po_no' => $c->po_no ?? '-',
                'serial_no' => $c->serial_no ?? '-',
                'description' => $c->description ?? '-',
                'brand' => $c->brand ?? '-',
                'coverage' => $c->coverage_months . ' month(s)',
                'start_date' => $c->start_date ? $c->start_date->format('M d, Y') : '-',
                'end_date' => $c->end_date ? $c->end_date->format('M d, Y') : '-',
                'is_active' => (bool) $c->is_active,
            ]
        );
    }

    public function activityLog(Request $request)
    {
        // Rows load via server-side DataTables (see activityLogData) because the
        // range is unbounded — clearing the dates selects the whole table.
        [$from, $to] = $this->activityLogRange($request);

        return view('reports.activity-log', [
            'from' => $from,
            'to' => $to,
            'isDefaultRange' => ! $request->filled('from_date') && ! $request->filled('to_date'),
            'canExport' => $this->canExportActivityLog(),
        ]);
    }

    /**
     * Who may extract the activity log: Super Admin, Developer and Admin only.
     * isSA() already covers Developer.
     */
    private function canExportActivityLog(): bool
    {
        $user = auth()->user();

        return $user && ($user->isSA() || $user->isAdmin());
    }

    public function activityLogData(Request $request)
    {
        [$from, $to] = $this->activityLogRange($request);

        // Joined to users so the User column can be searched and sorted by name
        // rather than by the raw foreign key.
        $base = ActivityLog::query()
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->whereBetween('activity_logs.created_at', [$from, $to])
            ->select('activity_logs.*', 'users.name as user_name');

        $recordsTotal = (clone $base)->count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                  ->orWhere('activity_logs.user_role', 'like', "%{$search}%")
                  ->orWhere('activity_logs.action', 'like', "%{$search}%")
                  ->orWhere('activity_logs.remarks', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $base)->count();

        $orderColumns = ['activity_logs.created_at', 'users.name', 'activity_logs.user_role', 'activity_logs.action'];
        $orderIdx = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $base->orderBy($orderColumns[$orderIdx] ?? 'activity_logs.created_at', $orderDir);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $base->skip($start)->take($length);
        }

        $data = $base->get()->map(fn ($log) => [
            'date' => $log->created_at ? $log->created_at->format('M d, Y h:i A') : '—',
            'user' => $log->user_name ?? '—',
            'role' => $log->user_role ?: '—',
            'action' => $log->action ?? '—',
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Streams the filtered range as CSV. Remarks are included here even though
     * the table no longer shows them — the export is where the detail belongs,
     * and it is why the date filter is allowed past 30 days at all.
     *
     * Streamed over a cursor so an all-time export is not hydrated at once.
     */
    public function activityLogCsv(Request $request)
    {
        // Enforced here, not just by hiding the button — this URL can be typed
        // straight into the address bar, and the export is the whole audit trail.
        abort_unless($this->canExportActivityLog(), 403, 'You are not allowed to export the activity log.');

        [$from, $to] = $this->activityLogRange($request);

        $filename = 'activity_log_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date & Time', 'User', 'Role', 'Action', 'Remarks']);

            $rows = ActivityLog::with('user')
                ->whereBetween('created_at', [$from, $to])
                ->orderByDesc('created_at')
                ->cursor();

            foreach ($rows as $log) {
                fputcsv($out, [
                    $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '',
                    $log->user->name ?? '',
                    $log->user_role ?? '',
                    $log->action ?? '',
                    $log->remarks ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Resolves the [from, to] the page, the table and the export all share, so
     * a CSV can never cover a different range than the rows on screen.
     *
     * With no dates supplied it is the last ACTIVITY_LOG_DAYS days. Supplied
     * dates are honoured however far back they reach, but must survive being
     * typed by hand into a query string, so unparseable values fall back.
     */
    private function activityLogRange(Request $request): array
    {
        $from = $this->parseDate($request->input('from_date'))?->startOfDay()
            ?? Carbon::now()->subDays(self::ACTIVITY_LOG_DAYS)->startOfDay();

        $to = $this->parseDate($request->input('to_date'))?->endOfDay()
            ?? Carbon::now()->endOfDay();

        // A backwards range would silently return nothing; read it as the two
        // boxes having been filled the wrong way round.
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function parseDate($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function requestStatus()
    {
        $pending = PartRequest::where('status', 'Pending')->count();
        $out = PartRequest::where('status', 'Out')->count();

        $partRequests = PartRequest::with('customer', 'itemPart', 'location', 'requestedBy')
            ->orderBy('pick_date', 'asc')
            ->get();

        return view('reports.request-status', compact('pending', 'out', 'partRequests'));
    }
}
