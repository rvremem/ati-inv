<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Receiving;
use App\Models\PartRequest;
use App\Models\Machine;
use App\Models\MachineRequest;
use App\Models\MachinePart;
use App\Models\DefectiveItem;
use App\Models\MaintenanceContract;
use App\Models\ItemPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReportController extends Controller
{
    use \App\Http\Controllers\Concerns\ScopesByHub;

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

    /**
     * Narrows a report to one hub, when the filter asks for it.
     *
     * Applied on top of the enforced scope rather than instead of it —
     * hubFilterId() refuses a branch the reader may not see, so this can only
     * ever show less than they are already entitled to.
     */
    /**
     * receiving/part_requests narrowed to one hub for the valuation screens.
     *
     * The enforced scope is applied first, then the requested hub on top —
     * so a reader still cannot reach a branch that is not theirs, and the
     * filter only ever subtracts.
     */
    private function hubScopedReceiving(?int $hubId)
    {
        $q = $this->applyHubScope(DB::table('receiving'), 'receiving.hub_id');

        return $hubId === null ? $q : $q->where('receiving.hub_id', $hubId);
    }

    private function hubScopedRequests(?int $hubId)
    {
        $q = $this->applyRequesterHubScope(DB::table('part_requests'));

        return $hubId === null
            ? $q
            : $q->whereIn('part_requests.requested_by',
                \App\Models\User::where('hub_id', $hubId)->select('id'));
    }
    private function filterByHub($query, Request $request, string $column): void
    {
        $hubId = $this->hubFilterId($request);

        if ($hubId !== null) {
            $query->where($column, $hubId);
        }
    }

    /**
     * The same, for a table with no hub of its own.
     *
     * Requests and machine requests record who raised them, so the hub is that
     * person's — the rule the global scopes already use, repeated here so the
     * filter and the scope cannot disagree about which branch a request is in.
     */
    private function filterByRequesterHub($query, Request $request, string $column): void
    {
        $hubId = $this->hubFilterId($request);

        if ($hubId !== null) {
            $query->whereIn($column, \App\Models\User::where('hub_id', $hubId)->select('id'));
        }
    }

    public function receivingReport()
    {
        // Rows load via server-side DataTables (see receivingData).
        return view('reports.receiving');
    }

    public function receivingData(Request $request)
    {
        // Part No / Description / Location come from relations - display only.
        // Column order matches the Outbound report, which carries the same
        // serial column in the same slot. Warranty is computed per row, so it
        // cannot be ordered on either.
        $orderColumns = ['date_received', 'ati_po', null, null, 'serial_no', 'quantity', null, 'status', null];

        $base = Receiving::query()->with('itemPart', 'location');
        $this->filterByHub($base, $request, 'receiving.hub_id');
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
                // Non-serialised parts are tracked by quantity and have none.
                'serial_no' => $r->serial_no ?? '-',
                'quantity' => $r->quantity,
                'location' => $r->location->code ?? '-',
                'status' => $r->status,
            ] + $this->warrantyColumns($r)
        );
    }

    /**
     * Whether a received unit is still under its part's warranty today.
     *
     * Counted from date_received to the day the report is run, not to the
     * To Date filter: that filter picks which receipts to list, and a unit
     * received in January is not under warranty today just because the
     * report was narrowed to January.
     */
    private function warrantyColumns(Receiving $r): array
    {
        if (blank($r->itemPart?->warranty)) {
            return ['warranty' => 'Not Set', 'warranty_expiry' => '-'];
        }

        $ends = $r->itemPart->warrantyEndsOn($r->date_received);

        if (! $ends) {
            // Text with no duration in it, e.g. "Lifetime" - shown rather than
            // guessed at, so it can be corrected on the part.
            return ['warranty' => 'Unknown', 'warranty_expiry' => $r->itemPart->warranty];
        }

        return [
            'warranty' => now()->startOfDay()->lte($ends) ? 'Under Warranty' : 'Expired',
            'warranty_expiry' => $ends->format('M d, Y'),
        ];
    }

    public function outboundReport()
    {
        return view('reports.outbound');
    }

    public function outboundData(Request $request)
    {
        $orderColumns = ['out_date', 'mris_no', null, null, 'serial_no', 'quantity', null, 'status'];

        $base = PartRequest::query()->with('itemPart', 'location')->whereNotNull('out_date');
        $this->filterByRequesterHub($base, $request, 'part_requests.requested_by');
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

        // Narrowed to one hub when asked. hubFilterId() has already refused
        // anything the reader may not see, so this only ever shows less.
        $hubId = $this->hubFilterId($request);

        $items = ItemPart::selectRaw("item_parts.id, item_parts.part_no, item_parts.description, item_parts.type, item_parts.min_stock, item_parts.unit_cost")
            ->get();

        $items->transform(function ($item) use ($asOf, $hubId) {
            // sum(quantity), not count(*): an unserialised row carries its
            // whole quantity, so counting rows reported 5 pieces as 1.
            $item->receiving_qty = (int) $this->hubScopedReceiving($hubId)
                ->where('item_part_id', $item->id)
                ->where('status', 'Receiving')
                ->where('created_at', '<=', $asOf)
                ->sum('quantity');

            $item->return_qty = (int) $this->hubScopedReceiving($hubId)
                ->where('item_part_id', $item->id)
                ->where('status', 'Return')
                ->where('created_at', '<=', $asOf)
                ->sum('quantity');

            // Backfilled stock that was already on the shelf — on hand, per
            // InventoryController::ON_HAND_STATUSES. Leaving it out reported
            // every part entered by batch upload as zero.
            $item->old_qty = (int) $this->hubScopedReceiving($hubId)
                ->where('item_part_id', $item->id)
                ->where('status', 'Old Stock')
                ->where('created_at', '<=', $asOf)
                ->sum('quantity');

            $item->out_qty = (int) $this->hubScopedRequests($hubId)
                ->where('item_part_id', $item->id)
                ->where('status', 'Out')
                ->where('created_at', '<=', $asOf)
                ->sum('quantity');

            $item->on_hand = ($item->receiving_qty + $item->return_qty + $item->old_qty) - $item->out_qty;

            return $item;
        });

        // Only what is actually on the shelf. A part with nothing left reads as
        // a row of zeros that says nothing, and pushes the stock people came to
        // look at further down the page — the catalogue itself is Item Parts.
        //
        // Filtered after the computation rather than in SQL because on_hand is
        // derived from three separate sub-queries per part, so there is no
        // column to have filtered on beforehand.
        //
        // The comparison is > 0, so a part that has gone negative — more issued
        // than ever received, which means the records are wrong somewhere — is
        // hidden here too. Parts Location still shows it.
        $items = $items->filter(fn ($item) => $item->on_hand > 0)->values();

        return view('reports.stock', compact('items', 'asOf'));
    }

    /**
     * Inventory valuation: what each part costs, how many are on hand, and what
     * that is worth.
     *
     * Super Admin and Developer only. Unit cost is hidden from every other role
     * on Item Parts, so a report that totals it has to be gated the same way —
     * otherwise the figure they cannot see on the catalogue screen is a click
     * away here. isSA() covers both roles.
     *
     * On-hand is computed the same way as stockReport(): units summed from
     * quantity, not rows, and limited to the reader's hub. The hub limit is
     * academic while only SA and Developer can reach this — both are
     * unrestricted — but it is applied rather than assumed, so the report stays
     * correct if the gate is ever widened.
     */
    public function costReport(Request $request)
    {
        return view('reports.cost', $this->costReportData($request));
    }

    /**
     * Shared by the screen and the CSV, so a downloaded total can never
     * disagree with the one on the page.
     */
    private function costReportData(Request $request): array
    {
        abort_unless(auth()->user()?->isSA(), 403, 'Only Super Admin and Developer may view costs.');

        $asOf = $request->as_of_date ? $request->as_of_date . ' 23:59:59' : now()->toDateTimeString();

        $hubId = $this->hubFilterId($request);

        $items = ItemPart::where('is_active', true)
            ->select('id', 'part_no', 'description', 'type', 'unit_cost')
            ->get();

        $items->transform(function ($item) use ($asOf, $hubId) {
            $received = (int) $this->hubScopedReceiving($hubId)
                ->where('item_part_id', $item->id)
                ->whereIn('status', InventoryController::ON_HAND_STATUSES)
                ->where('created_at', '<=', $asOf)
                ->sum('quantity');

            $out = (int) $this->hubScopedRequests($hubId)
                ->where('item_part_id', $item->id)
                ->where('status', 'Out')
                ->where('created_at', '<=', $asOf)
                ->sum('quantity');

            $item->on_hand = $received - $out;
            $item->total_value = (float) $item->unit_cost * $item->on_hand;

            return $item;
        });

        // Most valuable first: the point of a valuation is what the money is
        // sitting in, and a part number sort buries that.
        $items = $items->sortByDesc('total_value')->values();

        return [
            'asOf' => $asOf,
            'items' => $items,
            'grandTotal' => $items->sum('total_value'),
            'totalUnits' => $items->sum('on_hand'),
            // Parts priced at zero are counted separately rather than hidden:
            // a valuation that quietly omits them reads as complete when it is
            // not, and an unpriced part is usually a data gap worth chasing.
            'unpriced' => $items->where('unit_cost', '<=', 0)->where('on_hand', '>', 0)->count(),
        ];
    }

    /**
     * The same report as a CSV, which Excel opens directly.
     *
     * CSV rather than a real .xlsx because the project carries no spreadsheet
     * library, and adding one to write six columns is not worth the dependency.
     *
     * Figures are written unformatted — no thousands separators, no currency
     * symbol — because Excel reads "1,234.56" as text and will not sum a column
     * of it. The heading says what the currency is instead.
     */
    public function costReportCsv(Request $request)
    {
        $data = $this->costReportData($request);

        $stamp = Carbon::parse($data['asOf'])->format('Ymd');
        $filename = "cost_report_{$stamp}.csv";

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');

            // Excel assumes the system codepage without this and mangles any
            // non-ASCII character in a description.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Part No', 'Description', 'Type', 'Unit Cost (USD)', 'On Hand', 'Total Value (USD)']);

            foreach ($data['items'] as $item) {
                fputcsv($out, [
                    $item->part_no,
                    $item->description,
                    $item->type ?? '',
                    number_format((float) $item->unit_cost, 2, '.', ''),
                    $item->on_hand,
                    number_format((float) $item->total_value, 2, '.', ''),
                ]);
            }

            // Blank line first so the total reads as a summary rather than as
            // another part, and so a spreadsheet's autofilter stops above it.
            fputcsv($out, []);
            fputcsv($out, ['', '', '', 'TOTAL', $data['totalUnits'],
                number_format((float) $data['grandTotal'], 2, '.', '')]);

            // Carried into the file because the total understates by whatever
            // these are worth, and that caveat should travel with the number.
            if ($data['unpriced'] > 0) {
                fputcsv($out, []);
                fputcsv($out, ['Note', $data['unpriced'] . ' part(s) hold stock but have no unit cost, so the total above is understated.']);
            }

            fputcsv($out, []);
            fputcsv($out, ['As of', Carbon::parse($data['asOf'])->format('M d, Y')]);
            fputcsv($out, ['Generated', now()->format('M d, Y h:i A')]);
            fputcsv($out, ['Generated by', auth()->user()->name ?? '']);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
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
        $this->filterByHub($base, $request, 'machines.hub_id');
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

    /*
    |--------------------------------------------------------------------------
    | Machine Inbound / Outbound
    |--------------------------------------------------------------------------
    | The machine counterparts to the Receiving and Outbound reports, which
    | cover parts only. Kept separate from the Machines Report — that one is a
    | stock list ("what equipment do we hold"), these two are movement logs
    | ("what arrived, what went out, when").
    |
    | They mirror the parts reports deliberately: same filters, same column
    | order where the concepts line up, so the four read the same way.
    */

    public function machineInboundReport()
    {
        return view('reports.machine-inbound');
    }

    /**
     * Machines arriving: both first receipt and returns from a customer.
     *
     * Unlike parts, there is no separate receiving table for machines — the
     * machine row itself carries date_received and return_date. So the report
     * lists machines by date received and shows the return date alongside,
     * rather than joining anything.
     */
    public function machineInboundData(Request $request)
    {
        // Supplier / Location are relations - display only.
        $orderColumns = ['date_received', 'ati_po', 'serial_no', 'description', 'model', null, null, 'return_date', 'status'];

        $base = Machine::query()->with('supplier', 'location');
        $this->filterByHub($base, $request, 'machines.hub_id');

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
                    $q->where('serial_no', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('ati_po', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%")
                      ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
                });
            },
            'date_received',
            'desc',
            fn ($m) => [
                'date_received' => $m->date_received ? $m->date_received->format('M d, Y') : '-',
                'ati_po' => $m->ati_po ?? '-',
                'serial_no' => $m->serial_no,
                'description' => $m->description,
                'model' => $m->model ?? '-',
                'supplier' => $m->supplier->name ?? '-',
                'location' => $m->location->code ?? '-',
                // Only set on machines that came back from a customer.
                'return_date' => $m->return_date ? $m->return_date->format('M d, Y') : '-',
                'status' => $m->status,
            ]
        );
    }

    public function machineOutboundReport()
    {
        return view('reports.machine-outbound');
    }

    /**
     * Machines issued out, straight off machine_requests.
     *
     * whereNotNull('out_date') is the same gate the parts Outbound report uses:
     * a request that was raised but never released has no out date and is not
     * an outbound movement yet.
     */
    public function machineOutboundData(Request $request)
    {
        // Serial / Description / Model come off the machine relation, Client and
        // Location off theirs - display only.
        $orderColumns = ['out_date', 'mris_no', null, null, null, null, 'purpose', null, 'status'];

        $base = MachineRequest::query()
            ->with('machine', 'customer', 'location')
            ->whereNotNull('out_date');
        $this->filterByRequesterHub($base, $request, 'machine_requests.requested_by');

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
                      ->orWhere('purpose', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%")
                      ->orWhereHas('machine', fn ($m) => $m
                          ->where('serial_no', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhere('model', 'like', "%{$search}%"))
                      ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            },
            'out_date',
            'desc',
            fn ($r) => [
                'out_date' => $r->out_date ? $r->out_date->format('M d, Y') : '-',
                'mris_no' => $r->mris_no ?? '-',
                'serial_no' => $r->machine->serial_no ?? '-',
                'description' => $r->machine->description ?? '—',
                'model' => $r->machine->model ?? '-',
                'customer' => $r->customer->name ?? '-',
                'purpose' => $r->purpose ?? '-',
                'location' => $r->location->code ?? '-',
                'status' => $r->status,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Machine Parts — Assembly / Disassembly
    |--------------------------------------------------------------------------
    | A machine-part request is not a row in its own table: it is a status on
    | the machine_parts row itself, flipped 'available' -> 'Pending Out' -> 'Out'
    | as it is requested and approved. So this report reads that status as the
    | movement, and pairs it with the actor and time columns.
    |
    | Because the row is current state rather than an event log, it shows the
    | latest movement per part. A part fitted, pulled, and refitted reports as
    | assembled — the intermediate cycles are not retained.
    */

    /** Movement filter values, mapped to the machine_parts statuses behind them. */
    public const PART_MOVEMENTS = [
        'assembly' => MachinePart::STATUS_ASSEMBLED,
        'disassembly' => MachinePart::STATUS_DISASSEMBLED,
        'pending' => MachinePart::STATUS_PENDING,
    ];

    public function machinePartsReport()
    {
        return view('reports.machine-parts');
    }

    public function machinePartsData(Request $request)
    {
        // Machine / part / location / people are relations - display only. The
        // date and actor columns switch meaning per row, so they cannot be
        // ordered on a single database column either.
        $orderColumns = [null, 'status', null, null, null, 'serial_no', 'quantity', null, null, null];

        $base = MachinePart::query()->with([
            'machine.location', 'itemPart', 'location',
            'assembledBy', 'disassembledBy', 'requestedBy',
        ]);

        // Via the machine, not the requester: a part fitted when the
        // machine was built has no requester to attribute it to.
        if (($hubId = $this->hubFilterId($request)) !== null) {
            $base->whereIn('machine_parts.machine_id',
                Machine::withoutGlobalScope('hub')->where('hub_id', $hubId)->select('id'));
        }

        $movement = $request->input('movement');
        if ($movement && isset(self::PART_MOVEMENTS[$movement])) {
            $base->where('status', self::PART_MOVEMENTS[$movement]);
        }

        // Filtered against whichever timestamp the row's own movement uses, so
        // "disassembled last week" does not also match parts fitted last week.
        $dateColumn = match ($movement) {
            'disassembly' => 'disassembled_at',
            'assembly' => 'assembled_at',
            default => null,
        };

        if ($dateColumn) {
            if ($request->filled('from_date')) {
                $base->whereDate($dateColumn, '>=', $request->input('from_date'));
            }
            if ($request->filled('to_date')) {
                $base->whereDate($dateColumn, '<=', $request->input('to_date'));
            }
        } elseif ($request->filled('from_date') || $request->filled('to_date')) {
            // No movement chosen: match a row if either of its timestamps falls
            // in the window, otherwise the filter would silently drop every row
            // whose movement does not match the column picked.
            $from = $request->input('from_date');
            $to = $request->input('to_date');
            $base->where(function ($q) use ($from, $to) {
                foreach (['assembled_at', 'disassembled_at'] as $col) {
                    $q->orWhere(function ($q) use ($col, $from, $to) {
                        $q->whereNotNull($col);
                        if ($from) {
                            $q->whereDate($col, '>=', $from);
                        }
                        if ($to) {
                            $q->whereDate($col, '<=', $to);
                        }
                    });
                }
            });
        }

        if ($request->filled('machine_id')) {
            $base->where('machine_id', $request->input('machine_id'));
        }

        return $this->dataTableJson(
            $request,
            $base,
            $orderColumns,
            function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('serial_no', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%")
                      ->orWhere('purpose', 'like', "%{$search}%")
                      ->orWhereHas('machine', fn ($m) => $m
                          ->where('serial_no', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%"))
                      ->orWhereHas('itemPart', fn ($i) => $i
                          ->where('part_no', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%"))
                      ->orWhereHas('assembledBy', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                      ->orWhereHas('disassembledBy', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                });
            },
            'updated_at',
            'desc',
            function ($mp) {
                $isOut = $mp->status === MachinePart::STATUS_DISASSEMBLED;

                // Each row reports its own movement: a disassembled part shows
                // who pulled it and when, everything else shows the assembly.
                $date = $isOut ? $mp->disassembled_at : $mp->assembled_at;
                $actor = $isOut ? $mp->disassembledBy : $mp->assembledBy;

                return [
                    'date' => $date ? $date->format('M d, Y') : '-',
                    'movement' => match ($mp->status) {
                        MachinePart::STATUS_DISASSEMBLED => 'Disassembly',
                        MachinePart::STATUS_PENDING => 'Pending Disassembly',
                        default => 'Assembly',
                    },
                    'machine_serial' => $mp->machine->serial_no ?? '-',
                    'part_no' => $mp->itemPart->part_no ?? '-',
                    'description' => $mp->itemPart->description ?? '—',
                    'serial_no' => $mp->serial_no ?? '-',
                    'quantity' => $mp->quantity,
                    'location' => $mp->location->code ?? $mp->machine->location->code ?? '-',
                    'handled_by' => $actor->name ?? '-',
                    'requested_by' => $mp->requestedBy->name ?? '-',
                ];
            }
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

        // activity_logs has no hub column: an entry belongs to the hub of
        // whoever performed it. Rows with no user - a failed sign-in against
        // an address that matches no account - belong to nobody and so drop
        // out of a hub-filtered view, which is correct: they are not that
        // branch's activity.
        $this->filterByHub($base, $request, 'users.hub_id');

        $recordsTotal = (clone $base)->count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                  ->orWhere('activity_logs.user_role', 'like', "%{$search}%")
                  ->orWhere('activity_logs.action', 'like', "%{$search}%")
                  ->orWhere('activity_logs.remarks', 'like', "%{$search}%")
                  ->orWhere('activity_logs.ip_address', 'like', "%{$search}%");
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
            'user' => $log->user_name ?? $this->attemptedUsername($log) ?? '—',
            'role' => $log->user_role ?: '—',
            'action' => $log->action ?? '—',
            'remarks' => $log->remarks,
            // Blank for anything done from the console rather than a browser.
            'ip_address' => $log->ip_address ?: '—',
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * The email someone tried to sign in with, for a failed login that
     * matched no account.
     *
     * Those rows have no user_id — there is no such user — so the User column
     * would read as a dash and the row would not say what was attempted, which
     * is the entire point of logging it. The address is read back out of the
     * fixed remarks shape ActivityLog::logFailedLogin() writes, and marked so
     * it is never mistaken for a real account.
     *
     * Both prefixes are accepted: sign-in was by username until email login
     * replaced it, and rows logged before that still read "Username: x".
     * Matching only the current shape would blank the User column on every
     * historical row.
     *
     * Only failed logins are treated this way; every other row keeps showing a
     * dash when it has no user, and the full remarks stay in the CSV export.
     */
    private function attemptedUsername(ActivityLog $log): ?string
    {
        if ($log->action !== ActivityLog::ACTION_FAILED_LOGIN) {
            return null;
        }

        if (! preg_match('/^(?:Email|Username):\s*([^|]+)/', (string) $log->remarks, $matches)) {
            return null;
        }

        return trim($matches[1]) . ' (no such user)';
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
            fputcsv($out, ['Date & Time', 'User', 'Role', 'Action', 'Details', 'IP Address']);

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
                    $log->ip_address ?? '',
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

    /**
     * Reports whose CSV is produced straight from their own table endpoint.
     *
     * Slug => the method that already builds that report's rows. Exporting
     * through it rather than re-querying means the file cannot disagree with
     * the screen: same filters, same search, same columns, same formatting,
     * because it is literally the same code path.
     *
     * The two reports missing here render Blade tables instead of a DataTable
     * and have their own writers below.
     */
    public const CSV_REPORTS = [
        'receiving'        => ['receivingData',       'item_inbound'],
        'outbound'         => ['outboundData',        'item_outbound'],
        'defective'        => ['defectiveData',       'defective_items'],
        'machines'         => ['machineData',         'machines'],
        'machine-inbound'  => ['machineInboundData',  'machine_inbound'],
        'machine-outbound' => ['machineOutboundData', 'machine_outbound'],
        'machine-parts'    => ['machinePartsData',    'machine_parts'],
        'maintenance'      => ['maintenanceData',     'maintenance_contracts'],
    ];

    /**
     * CSV for any of the DataTables-backed reports.
     *
     * length=0 is what turns pagination off in dataTableJson(), so this gets
     * every matching row rather than the page on screen. Every other filter
     * arrives on the query string exactly as the table sent it.
     */
    public function exportCsv(Request $request, string $report)
    {
        abort_unless(isset(self::CSV_REPORTS[$report]), 404);

        [$method, $slug] = self::CSV_REPORTS[$report];

        $rows = $this->{$method}($request->merge(['length' => 0]))->getData(true)['data'] ?? [];

        return $this->streamRowsAsCsv($rows, $slug);
    }

    /**
     * Writes already-mapped rows out as CSV.
     *
     * Headers come from the row keys rather than a hand-written list, so a
     * column added to a report turns up in its export without anyone
     * remembering to update two places.
     */
    private function streamRowsAsCsv(array $rows, string $slug, array $trailer = [])
    {
        $filename = $slug . '_' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows, $trailer) {
            $out = fopen('php://output', 'w');

            // Excel reads the file as the system codepage without this and
            // mangles any non-ASCII character in a description.
            fwrite($out, "\xEF\xBB\xBF");

            if (empty($rows)) {
                fputcsv($out, ['No records found for the selected filters.']);
            } else {
                fputcsv($out, array_map(fn ($k) => $this->csvHeading($k), array_keys($rows[0])));

                foreach ($rows as $row) {
                    // strip_tags guards against a report that later renders a
                    // badge or link in a cell; today none do.
                    fputcsv($out, array_map(
                        fn ($v) => is_scalar($v) || $v === null
                            ? html_entity_decode(strip_tags((string) $v), ENT_QUOTES)
                            : json_encode($v),
                        $row
                    ));
                }
            }

            foreach ($trailer as $line) {
                fputcsv($out, $line);
            }

            fputcsv($out, []);
            fputcsv($out, ['Generated', now()->format('M d, Y h:i A')]);
            fputcsv($out, ['Generated by', auth()->user()->name ?? '']);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * A row key as a column heading.
     *
     * Str::headline alone title-cases every word, which turns ati_po into
     * "Ati Po" and mris_no into "Mris No". These are the abbreviations this
     * system actually uses, restored afterwards so the exported headings read
     * the way the screens label them.
     */
    private function csvHeading(string $key): string
    {
        $heading = Str::headline($key);

        return strtr($heading, [
            'Ati Po' => 'ATI PO',
            'Mris No' => 'MRIS No',
            'Ip Address' => 'IP Address',
            'Po No' => 'PO No',
        ]);
    }

    /**
     * Stock report CSV. Rendered from Blade rather than a DataTable, so it has
     * no data endpoint to borrow; the rows are rebuilt from the same method the
     * page uses.
     */
    public function stockReportCsv(Request $request)
    {
        $items = $this->stockReport($request)->getData()['items'];

        $rows = $items->map(fn ($i) => [
            'part_no' => $i->part_no,
            'description' => $i->description,
            'type' => $i->type ?? '',
            'min_stock' => $i->min_stock,
            'on_hand' => $i->on_hand,
        ])->all();

        return $this->streamRowsAsCsv($rows, 'stock_report', [
            [],
            ['', '', '', 'TOTAL ON HAND', $items->sum('on_hand')],
        ]);
    }

    /** Request status CSV, same Blade-rendered case as the stock report. */
    public function requestStatusCsv(Request $request)
    {
        // The request is passed through so the download honours the hub filter
        // the screen is showing, rather than exporting every branch.
        $data = $this->requestStatus($request)->getData();

        $rows = $data['partRequests']->map(fn ($r) => [
            'pick_date' => $r->pick_date ? Carbon::parse($r->pick_date)->format('M d, Y') : '',
            'mris_no' => $r->mris_no ?? '',
            'customer' => $r->customer->name ?? '',
            'part_no' => $r->itemPart->part_no ?? '',
            'description' => $r->itemPart->description ?? '',
            'serial_no' => $r->serial_no ?? '',
            'quantity' => $r->quantity,
            'location' => $r->location->code ?? '',
            'requested_by' => $r->requestedBy->name ?? '',
            'status' => $r->status,
        ])->all();

        return $this->streamRowsAsCsv($rows, 'request_status', [
            [],
            ['Pending', $data['pending']],
            ['Out', $data['out']],
        ]);
    }
    public function requestStatus(Request $request)
    {
        // A fresh builder per figure — the two counts and the table all need
        // the same hub filter, and reusing one builder would stack their
        // where clauses on top of each other.
        $scoped = function () use ($request) {
            $query = PartRequest::query();
            $this->filterByRequesterHub($query, $request, 'part_requests.requested_by');

            return $query;
        };

        $pending = $scoped()->where('status', 'Pending')->count();
        $out = $scoped()->where('status', 'Out')->count();

        $partRequests = $scoped()->with('customer', 'itemPart', 'location', 'requestedBy')
            ->orderBy('pick_date', 'asc')
            ->get();

        return view('reports.request-status', compact('pending', 'out', 'partRequests'));
    }
}
