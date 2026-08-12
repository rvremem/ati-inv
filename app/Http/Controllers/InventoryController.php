<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ItemPart;
use App\Models\Location;
use App\Models\Customer;
use App\Models\Category;
use App\Models\Machine;
use App\Models\MachinePart;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Engineers get Item Parts read-only: no Actions column, no Add Item.
     * Every other role is unchanged.
     */
    private function canManageItems(): bool
    {
        $user = auth()->user();

        return $user && ! $user->isEngineer();
    }

    /** Guards the write endpoints — hiding the buttons alone leaves the routes open. */
    private function authorizeItemManagement(): void
    {
        abort_unless($this->canManageItems(), 403, 'You are not allowed to manage item parts.');
    }

    public function items()
    {
        // Rows are loaded via server-side DataTables (see itemsData) so the page
        // shell renders instantly instead of dumping the whole catalogue - plus a
        // JSON copy of every row for the edit modal - into the DOM.
        $customers = Customer::where('is_active', true)->get();
        return view('inventory.items.index', [
            'customers' => $customers,
            'canManage' => $this->canManageItems(),
        ]);
    }

    public function itemsData(Request $request)
    {
        // Server-side DataTables endpoint: search + order + paginate in SQL,
        // returning only the current page of rows. Each row is sent as the full
        // model array because the edit modal is populated straight from it.
        $orderColumns = ['part_no', 'description', 'manufacturer', 'type', 'unit_cost', 'is_active'];

        // Matches the previous ItemPart::get() - soft-deleted rows are excluded
        // by the model, but inactive ones still show with an "Inactive" badge.
        $base = ItemPart::query();

        // Unfiltered total (before the search box is applied).
        $recordsTotal = ItemPart::count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                foreach (['part_no', 'description', 'manufacturer', 'model', 'type'] as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

        $recordsFiltered = (clone $base)->count();

        $orderIdx = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        if ($orderIdx !== null && isset($orderColumns[$orderIdx])) {
            $base->orderBy($orderColumns[$orderIdx], $orderDir);
        } else {
            $base->orderBy('part_no', 'asc');
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
            'data' => $base->get()->map(fn ($item) => $item->toArray()),
        ]);
    }

    public function itemCreate()
    {
        $this->authorizeItemManagement();

        return view('inventory.items.create');
    }

    public function itemStore(Request $request)
    {
        $this->authorizeItemManagement();

        $validated = $request->validate([
            'part_no' => 'required|unique:item_parts',
            'description' => 'required',
            'manufacturer' => 'nullable',
            'model' => 'nullable',
            'unit_cost' => 'required|numeric',
            'min_stock' => 'required|integer',
            'type' => 'required',
            'is_serialized' => 'boolean',
            'remarks' => 'nullable',
        ]);

        $item = ItemPart::create($validated);
        ActivityLog::log('Created Item', "Part No: {$item->part_no} | {$item->description}");
        return redirect()->route('inventory.items')->with('success', 'Item created successfully.');
    }

    public function itemEdit(ItemPart $item)
    {
        $this->authorizeItemManagement();

        return view('inventory.items.edit', compact('item'));
    }

    public function itemUpdate(Request $request, ItemPart $item)
    {
        $this->authorizeItemManagement();

        $validated = $request->validate([
            'part_no' => "required|unique:item_parts,part_no,{$item->id}",
            'description' => 'required',
            'manufacturer' => 'nullable',
            'model' => 'nullable',
            'unit_cost' => 'required|numeric',
            'min_stock' => 'required|integer',
            'type' => 'required',
            'is_serialized' => 'boolean',
            'remarks' => 'nullable',
            'is_active' => 'boolean',
        ]);

        $validated['is_serialized'] = $request->boolean('is_serialized');
        $validated['is_active'] = $request->boolean('is_active');

        $item->update($validated);
        ActivityLog::log('Updated Item', "Part No: {$item->part_no} | {$item->description}");
        return redirect()->route('inventory.items')->with('success', 'Item updated successfully.');
    }

    public function itemDelete(ItemPart $item)
    {
        $this->authorizeItemManagement();

        ActivityLog::log('Deleted Item', "Part No: {$item->part_no} | {$item->description}");
        $item->delete();
        return redirect()->route('inventory.items')->with('success', 'Item deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Parts Location
    |--------------------------------------------------------------------------
    | Where each part number physically sits, and how much of it is there. One
    | row per (part, location) that still has stock, with an action to move a
    | whole bucket to another location — singly or by CSV.
    */

    /** The receiving statuses that mean the stock is on the shelf. */
    public const ON_HAND_STATUSES = ['Receiving', 'Return'];

    /**
     * Batch-move CSV columns. Not table fields — this upload moves stock rather
     * than inserting it, so a row names the bucket and where it should go.
     */
    public const PARTS_LOCATION_BATCH_COLUMNS = ['part_no', 'old_location', 'new_location'];

    /**
     * On-hand per (item_part_id, location_id), as a bare SQL string.
     *
     * This is the Stock Levels figure partitioned by location, so the two can
     * never disagree — note stocksData() counts *rows*, not the quantity
     * column, so this counts rows too.
     *
     * Outs are netted against the location they were booked to. Anything that
     * cannot be netted in place — an out recorded against a location that never
     * held the part, or that held less than was taken — spills onto the part's
     * remaining locations, largest first. Without the spill those outs would
     * sit in a negative row, be dropped by the on_hand > 0 filter, and the page
     * would report more stock than Stock Levels does.
     *
     * Built from nested derived tables, not CTEs: SQL Server cannot wrap a CTE
     * as a subquery, and the DataTables endpoint needs to join and page over
     * this. NULL location_id collapses to 0 so it groups and joins consistently
     * (locations.id starts at 1, so 0 is a safe sentinel).
     */
    private function onHandByLocationSql(): string
    {
        $avail = "'" . implode("','", self::ON_HAND_STATUSES) . "'";

        return "
            SELECT item_part_id, location_id, on_hand
            FROM (
                SELECT item_part_id, location_id,
                       surplus - CASE
                           WHEN deficit - prior <= 0 THEN 0
                           WHEN deficit - prior >= surplus THEN surplus
                           ELSE deficit - prior
                       END AS on_hand
                FROM (
                    SELECT item_part_id, location_id, surplus,
                           SUM(shortfall) OVER (PARTITION BY item_part_id) AS deficit,
                           ISNULL(SUM(surplus) OVER (
                               PARTITION BY item_part_id ORDER BY surplus DESC, location_id
                               ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                           ), 0) AS prior
                    FROM (
                        SELECT item_part_id, location_id,
                               CASE WHEN net > 0 THEN net ELSE 0 END AS surplus,
                               CASE WHEN net < 0 THEN -net ELSE 0 END AS shortfall
                        FROM (
                            SELECT COALESCE(i.item_part_id, o.item_part_id) AS item_part_id,
                                   COALESCE(i.location_id, o.location_id) AS location_id,
                                   ISNULL(i.qty, 0) - ISNULL(o.qty, 0) AS net
                            FROM (
                                SELECT item_part_id, ISNULL(location_id, 0) AS location_id, COUNT(*) AS qty
                                FROM receiving
                                WHERE status IN ($avail)
                                GROUP BY item_part_id, ISNULL(location_id, 0)
                            ) i
                            FULL OUTER JOIN (
                                SELECT item_part_id, ISNULL(location_id, 0) AS location_id, COUNT(*) AS qty
                                FROM part_requests
                                WHERE status = 'Out'
                                GROUP BY item_part_id, ISNULL(location_id, 0)
                            ) o ON o.item_part_id = i.item_part_id AND o.location_id = i.location_id
                        ) n
                    ) s
                ) a
            ) f
            WHERE on_hand > 0
        ";
    }

    public function partsLocation()
    {
        // Only active locations are offered as a move target; the list itself
        // still shows stock parked on retired ones, because moving it off them
        // is the point of the page.
        $locations = Location::where('is_active', true)->orderBy('code')->get();

        return view('inventory.parts-location.index', compact('locations'));
    }

    public function partsLocationData(Request $request)
    {
        $base = DB::table(DB::raw('(' . $this->onHandByLocationSql() . ') AS pl'))
            ->join('item_parts as ip', 'ip.id', '=', 'pl.item_part_id')
            ->leftJoin('locations as l', 'l.id', '=', 'pl.location_id')
            // item_parts uses SoftDeletes; raw SQL bypasses the model scope.
            ->whereNull('ip.deleted_at')
            ->select('ip.part_no', 'ip.description', 'pl.on_hand', 'l.code as location_code', 'l.description as location_desc');

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('ip.part_no', 'like', "%{$search}%")
                  ->orWhere('ip.description', 'like', "%{$search}%")
                  ->orWhere('l.code', 'like', "%{$search}%")
                  ->orWhere('l.description', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $base)->count();
        $recordsTotal = $search === ''
            ? $recordsFiltered
            : DB::table(DB::raw('(' . $this->onHandByLocationSql() . ') AS pl'))
                ->join('item_parts as ip', 'ip.id', '=', 'pl.item_part_id')
                ->whereNull('ip.deleted_at')
                ->count();

        // Column order matches the view's headers.
        $orderCols = ['ip.part_no', 'ip.description', 'l.code', 'pl.on_hand'];
        $idx = $request->input('order.0.column');
        $dir = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        $base->orderBy(($idx !== null && isset($orderCols[$idx])) ? $orderCols[$idx] : 'ip.part_no', $dir)
             ->orderBy('l.code');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $base->skip($start)->take($length);
        }

        // Quantity only, by design — matching atiis, where a serial count could
        // not be reconciled with the quantity on legacy rows.
        $data = $base->get()->map(fn ($row) => [
            'part_no' => $row->part_no,
            'description' => $row->description,
            'location' => $row->location_code,
            'location_desc' => $row->location_desc,
            'on_hand' => (int) $row->on_hand,
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * The receiving rows holding the stock shown for one (part, location) row.
     *
     * Non-serialised rows always qualify. A serialised row qualifies only while
     * its serial is still available: once issued, its receiving row is history
     * and must stay put, or the out that nets it off is stranded at the old
     * location.
     *
     * Note this scopes the part_requests branch by item_part_id, which
     * availableSerialsFor() does not — see the note in the parity doc about
     * serials leaking across items there.
     */
    private function movableReceivingIds(int $itemPartId, int $locationId): array
    {
        $avail = "'" . implode("','", self::ON_HAND_STATUSES) . "'";
        $real = "serial_no IS NOT NULL AND LTRIM(RTRIM(serial_no)) <> ''";

        $rows = DB::select("
            SELECT id
            FROM receiving
            WHERE item_part_id = ?
              AND ISNULL(location_id, 0) = ?
              AND status IN ($avail)
              AND (
                  serial_no IS NULL OR LTRIM(RTRIM(serial_no)) = ''
                  OR serial_no IN (
                      SELECT serial_no FROM (
                          SELECT serial_no, status, ROW_NUMBER() OVER (
                                     PARTITION BY serial_no ORDER BY created_at DESC, src DESC
                                 ) AS rn
                          FROM (
                              SELECT serial_no, status, created_at, 0 AS src
                              FROM receiving WHERE item_part_id = ? AND $real
                              UNION ALL
                              SELECT serial_no, status, created_at, 1
                              FROM part_requests
                              WHERE item_part_id = ? AND status <> 'Denied' AND $real
                          ) e
                      ) x WHERE rn = 1 AND status IN ($avail)
                  )
              )
        ", [$itemPartId, $locationId, $itemPartId, $itemPartId]);

        return array_map(fn ($row) => $row->id, $rows);
    }

    private function onHandAt(int $itemPartId, int $locationId): int
    {
        $row = DB::selectOne(
            'SELECT on_hand FROM (' . $this->onHandByLocationSql() . ') AS pl WHERE item_part_id = ? AND location_id = ?',
            [$itemPartId, $locationId]
        );

        return (int) ($row->on_hand ?? 0);
    }

    /** Re-points every receiving row behind one bucket. Null if nothing to move. */
    private function relocateParts(int $itemPartId, int $fromLocationId, int $toLocationId): ?int
    {
        $ids = $this->movableReceivingIds($itemPartId, $fromLocationId);

        if (empty($ids)) {
            return null;
        }

        $moved = 0;
        // Chunked: SQL Server caps a statement at 2,100 parameters.
        foreach (array_chunk($ids, 500) as $chunk) {
            $moved += DB::table('receiving')->whereIn('id', $chunk)->update([
                'location_id' => $toLocationId,
                'location_updated_at' => now(),
                'location_updated_by' => auth()->user()->name,
                'updated_at' => now(),
            ]);
        }

        return $moved;
    }

    public function partsLocationMove(Request $request)
    {
        $validated = $request->validate([
            'part_no' => 'required|string',
            'from_location' => 'required|string',
            'to_location' => 'required|string|exists:locations,code',
        ]);

        $part = ItemPart::where('part_no', trim($validated['part_no']))->first();
        $from = Location::where('code', trim($validated['from_location']))->first();
        $to = Location::where('code', trim($validated['to_location']))->first();

        if (! $part || ! $from || ! $to) {
            return back()->with('error', 'Unknown part number or location.');
        }

        if ($from->id === $to->id) {
            return back()->with('error', 'The new location is the same as the current one.');
        }

        if (! $to->is_active) {
            return back()->with('error', "Location {$to->code} is not active.");
        }

        $onHand = $this->onHandAt($part->id, $from->id);
        if ($onHand <= 0) {
            return back()->with('error', "{$part->part_no} has no available stock at {$from->code}.");
        }

        $moved = $this->relocateParts($part->id, $from->id, $to->id);
        if ($moved === null) {
            return back()->with('error', "Nothing movable found for {$part->part_no} at {$from->code}.");
        }

        ActivityLog::log('Moved Part Location', "{$part->part_no}: {$from->code} -> {$to->code} | qty {$onHand} ({$moved} receiving row(s))");

        return redirect()->route('inventory.parts-location')
            ->with('success', "Moved {$onHand} × {$part->part_no} from {$from->code} to {$to->code}.");
    }

    public function partsLocationBatchUpload()
    {
        return view('inventory.parts-location.batch-upload', [
            'sampleRows' => $this->partsLocationSampleRows(),
        ]);
    }

    public function partsLocationSampleCsv()
    {
        $columns = self::PARTS_LOCATION_BATCH_COLUMNS;
        $rows = $this->partsLocationSampleRows();

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'parts_location_template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Rows built from stock really on the shelf, so the spellings are
     * known-good. new_location stays an obvious placeholder on purpose: unlike
     * every other batch upload this one *changes* existing records, so
     * uploading the template unedited must not quietly move anything.
     */
    private function partsLocationSampleRows(): array
    {
        $rows = DB::select(
            'SELECT TOP 2 ip.part_no, l.code
             FROM (' . $this->onHandByLocationSql() . ') AS pl
             JOIN item_parts ip ON ip.id = pl.item_part_id
             JOIN locations l ON l.id = pl.location_id
             WHERE ip.deleted_at IS NULL
             ORDER BY pl.on_hand DESC, ip.part_no'
        );

        $samples = array_map(fn ($r) => [$r->part_no, $r->code, 'NEW-LOCATION-CODE'], $rows);

        return count($samples) === 2 ? $samples : [
            ['PART-001', 'HO', 'NEW-LOCATION-CODE'],
            ['PART-002', 'CEBU', 'NEW-LOCATION-CODE'],
        ];
    }

    public function partsLocationBatchStore(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt']);

        $records = array_map('str_getcsv', file($request->file('csv_file')->getRealPath()));
        $header = array_map('trim', array_shift($records) ?: []);

        $activeLocations = Location::where('is_active', true)->get()
            ->mapWithKeys(fn ($l) => [strtolower(trim($l->code)) => $l]);

        $moved = 0;
        $skipped = 0;
        $reasons = [];

        foreach ($records as $row) {
            if (count($row) !== count($header)) {
                $skipped++;
                $reasons[] = 'column count does not match the header';
                continue;
            }

            $data = array_intersect_key(array_combine($header, $row), array_flip(self::PARTS_LOCATION_BATCH_COLUMNS));

            $partNo = trim((string) ($data['part_no'] ?? ''));
            $fromCode = trim((string) ($data['old_location'] ?? ''));
            $toCode = trim((string) ($data['new_location'] ?? ''));

            if ($partNo === '' || $fromCode === '' || $toCode === '') {
                $skipped++;
                $reasons[] = 'part_no, old_location and new_location are all required';
                continue;
            }

            $to = $activeLocations->get(strtolower($toCode));
            if (! $to) {
                $skipped++;
                $reasons[] = "{$partNo}: {$toCode} is not an active location";
                continue;
            }

            $part = ItemPart::where('part_no', $partNo)->first();
            $from = Location::where('code', $fromCode)->first();

            if (! $part || ! $from) {
                $skipped++;
                $reasons[] = "{$partNo}: unknown part number or location {$fromCode}";
                continue;
            }

            if ($from->id === $to->id) {
                $skipped++;
                $reasons[] = "{$partNo}: already at {$to->code}";
                continue;
            }

            // The availability gate: a bucket only exists while it still has
            // on-hand stock, so parts that are out cannot be moved.
            if ($this->onHandAt($part->id, $from->id) <= 0) {
                $skipped++;
                $reasons[] = "{$partNo}: no available stock at {$from->code}";
                continue;
            }

            try {
                if ($this->relocateParts($part->id, $from->id, $to->id) === null) {
                    $skipped++;
                    $reasons[] = "{$partNo}: nothing movable at {$from->code}";
                    continue;
                }
                $moved++;
            } catch (\Throwable $e) {
                $skipped++;
                $reasons[] = "{$partNo}: {$e->getMessage()}";
            }
        }

        ActivityLog::log('Batch Moved Part Locations', "{$moved} moved, {$skipped} skipped");

        $message = "Moved {$moved} part/location bucket(s).";
        if ($skipped > 0) {
            $unique = array_unique($reasons);
            $message .= " {$skipped} row(s) skipped: " . implode('; ', array_slice($unique, 0, 5))
                . (count($unique) > 5 ? '; …' : '.');
        }

        return redirect()->route('inventory.parts-location')->with('success', $message);
    }

    public function locations()
    {
        $locations = Location::paginate(15);
        return view('inventory.locations.index', compact('locations'));
    }

    public function locationCreate()
    {
        return view('inventory.locations.create');
    }

    public function locationStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|unique:locations',
            'description' => 'required',
        ]);

        $validated['created_by'] = auth()->id();
        $location = Location::create($validated);
        ActivityLog::log('Created Location', "Code: {$location->code} | {$location->description}");
        return redirect()->route('inventory.locations')->with('success', 'Location created successfully.');
    }

    public function locationEdit(Location $location)
    {
        return view('inventory.locations.edit', compact('location'));
    }

    public function locationUpdate(Request $request, Location $location)
    {
        $validated = $request->validate([
            'code' => "required|unique:locations,code,{$location->id}",
            'description' => 'required',
        ]);

        $location->update($validated);
        ActivityLog::log('Updated Location', "Code: {$location->code} | {$location->description}");
        return redirect()->route('inventory.locations')->with('success', 'Location updated successfully.');
    }

    public function locationDelete(Location $location)
    {
        ActivityLog::log('Deleted Location', "Code: {$location->code} | {$location->description}");
        $location->delete();
        return redirect()->route('inventory.locations')->with('success', 'Location deleted successfully.');
    }

    public function locationBatchUpload()
    {
        return view('inventory.locations.batch-upload');
    }

    public function locationBatchStore(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $records = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_map('trim', array_shift($records));

        $imported = 0;
        $skipped = 0;
        foreach ($records as $row) {
            if (count($row) < 2) {
                continue;
            }

            $data = array_combine($header, $row);
            $code = trim($data['code'] ?? '');
            $description = trim($data['description'] ?? '');

            if ($code === '' || $description === '' || Location::where('code', $code)->exists()) {
                $skipped++;
                continue;
            }

            $isActive = filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

            Location::create([
                'code' => $code,
                'description' => $description,
                'is_active' => $isActive,
                'created_by' => auth()->id(),
            ]);
            $imported++;
        }

        ActivityLog::log('Batch Uploaded Locations', "{$imported} location(s) imported, {$skipped} skipped");
        return redirect()->route('inventory.locations')->with('success', "Imported {$imported} location(s) successfully." . ($skipped ? " {$skipped} row(s) skipped (duplicate code or missing data)." : ''));
    }

    public function stocks()
    {
        // Rows are loaded via server-side DataTables (see stocksData) so the page
        // shell renders instantly instead of running the per-item stock queries
        // for the whole catalogue up front.
        return view('inventory.stocks');
    }

    public function stocksData(Request $request)
    {
        // Server-side DataTables endpoint. The per-item counts that used to run
        // as separate queries per row are folded into two grouped sub-selects so
        // search / order / paginate all happen in SQL; only the serial lookup
        // stays per-row, and now runs for the current page only.
        $orderColumns = [
            'item_parts.part_no',
            'item_parts.description',
            null,               // Old Stock is a constant 0 - nothing to sort on
            'receiving_qty',
            'return_qty',
            'out_qty',
            'total_qty',
        ];

        $received = DB::table('receiving')
            ->select(
                'item_part_id',
                DB::raw("SUM(CASE WHEN status = 'Receiving' THEN 1 ELSE 0 END) as recv_qty"),
                DB::raw("SUM(CASE WHEN status = 'Return' THEN 1 ELSE 0 END) as ret_qty")
            )
            ->groupBy('item_part_id');

        $out = DB::table('part_requests')
            ->select('item_part_id', DB::raw('COUNT(*) as out_qty'))
            ->where('status', 'Out')
            ->groupBy('item_part_id');

        $base = ItemPart::query()
            ->leftJoinSub($received, 'r', fn ($join) => $join->on('r.item_part_id', '=', 'item_parts.id'))
            ->leftJoinSub($out, 'o', fn ($join) => $join->on('o.item_part_id', '=', 'item_parts.id'))
            ->select(
                'item_parts.id',
                'item_parts.part_no',
                'item_parts.description',
                'item_parts.type',
                'item_parts.unit_cost',
                DB::raw('COALESCE(r.recv_qty, 0) as receiving_qty'),
                DB::raw('COALESCE(r.ret_qty, 0) as return_qty'),
                DB::raw('COALESCE(o.out_qty, 0) as out_qty'),
                // Old Stock is 0, so on-hand is receiving + return - out.
                DB::raw('(COALESCE(r.recv_qty, 0) + COALESCE(r.ret_qty, 0) - COALESCE(o.out_qty, 0)) as total_qty')
            );

        // Unfiltered total (before the search box is applied).
        $recordsTotal = ItemPart::count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                foreach (['part_no', 'description', 'type'] as $col) {
                    $q->orWhere("item_parts.{$col}", 'like', "%{$search}%");
                }
            });
        }

        $recordsFiltered = (clone $base)->count();

        $orderIdx = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        if ($orderIdx !== null && ($orderColumns[$orderIdx] ?? null) !== null) {
            $base->orderBy($orderColumns[$orderIdx], $orderDir);
        } else {
            $base->orderBy('item_parts.part_no', 'asc');
        }

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            // sqlsrv needs the ORDER BY above before OFFSET/FETCH is applied.
            $base->skip($start)->take($length);
        }

        $data = $base->get()->map(function ($item) {
            return [
                'part_no' => $item->part_no,
                'description' => $item->description,
                'old_stock' => 0,
                'receiving_qty' => (int) $item->receiving_qty,
                'return_qty' => (int) $item->return_qty,
                'out_qty' => (int) $item->out_qty,
                'total_qty' => (int) $item->total_qty,
                'available_serials' => $this->availableSerialsFor($item->id),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Serials whose latest status across receiving + part_requests is still
     * 'Receiving' or 'Return' — i.e. physically on hand.
     */
    private function availableSerialsFor($itemPartId): array
    {
        $serials = DB::select("
            WITH combined AS (
                SELECT serial_no, status, created_at
                FROM receiving
                WHERE item_part_id = ? AND serial_no IS NOT NULL
                UNION ALL
                SELECT serial_no, status, created_at
                FROM part_requests
                WHERE serial_no IS NOT NULL AND status != 'Denied'
            ),
            ranked AS (
                SELECT *,
                       ROW_NUMBER() OVER (PARTITION BY serial_no ORDER BY created_at DESC) as rn
                FROM combined
            )
            SELECT serial_no
            FROM ranked
            WHERE rn = 1 AND status IN ('Receiving', 'Return')
        ", [$itemPartId]);

        return array_map(fn ($s) => $s->serial_no, $serials);
    }

    public function getAvailableSerials($itemId)
    {
        // Get serials with their latest status (max created_at from both receiving and part_requests)
        // Show only serials where latest status = 'Receiving' or 'Return'
        $serials = DB::select("
            WITH combined AS (
                SELECT serial_no, item_part_id, location_id, quantity, status, created_at
                FROM receiving
                WHERE item_part_id = ? AND serial_no IS NOT NULL
                UNION ALL
                SELECT serial_no, NULL, NULL, NULL, status, created_at
                FROM part_requests
                WHERE serial_no IS NOT NULL AND status != 'Denied'
            ),
            ranked AS (
                SELECT *,
                       ROW_NUMBER() OVER (PARTITION BY serial_no ORDER BY created_at DESC) as rn
                FROM combined
            )
            SELECT serial_no
            FROM ranked
            WHERE rn = 1 AND status IN ('Receiving', 'Return')
        ", [$itemId]);

        $serialNumbers = array_map(fn($s) => $s->serial_no, $serials);

        return response()->json([
            'serials' => $serialNumbers,
        ]);
    }

    public function getSerialLocation($serial)
    {
        // Find the location where this serial was received
        $receiving = DB::table('receiving')
            ->where('serial_no', $serial)
            ->select('location_id')
            ->first();

        if ($receiving && $receiving->location_id) {
            $location = Location::find($receiving->location_id);
            if ($location) {
                return response()->json([
                    'location' => $location->code,
                    'location_id' => $location->id,
                ]);
            }
        }

        return response()->json([
            'location' => null,
            'location_id' => null,
        ]);
    }

    /**
     * Where a part is currently stocked, looked up by item rather than by serial.
     *
     * getSerialLocation() resolves one specific unit; this is the fallback for
     * non-serialised parts, which are tracked by quantity and so have no serial
     * to look up. Used by the Pending Approvals screen to pre-fill the location
     * on the approve form.
     */
    public function getPartLocation($itemId)
    {
        // Newest on-hand receiving row for this part that actually has a location.
        $receiving = DB::table('receiving')
            ->where('item_part_id', $itemId)
            ->whereIn('status', ['Receiving', 'Return'])
            ->whereNotNull('location_id')
            ->orderByDesc('created_at')
            ->select('location_id')
            ->first();

        if ($receiving && $receiving->location_id) {
            $location = Location::find($receiving->location_id);
            if ($location) {
                return response()->json([
                    'location' => $location->code,
                    'location_id' => $location->id,
                ]);
            }
        }

        return response()->json([
            'location' => null,
            'location_id' => null,
        ]);
    }

    public function getReturnableSerials()
    {
        // Get serials with their latest status (max created_at from both receiving and part_requests)
        // Show only serials where latest status = 'Out'
        $serials = DB::select("
            WITH combined AS (
                SELECT serial_no, status, created_at
                FROM receiving
                WHERE serial_no IS NOT NULL
                UNION ALL
                SELECT serial_no, status, created_at
                FROM part_requests
                WHERE serial_no IS NOT NULL
            ),
            ranked AS (
                SELECT *,
                       ROW_NUMBER() OVER (PARTITION BY serial_no ORDER BY created_at DESC) as rn
                FROM combined
            )
            SELECT serial_no
            FROM ranked
            WHERE rn = 1 AND status = 'Out'
            ORDER BY created_at DESC
        ");

        $serialNumbers = array_map(fn($item) => $item->serial_no, $serials);
        return response()->json(['serials' => $serialNumbers]);
    }

    public function getPartBySerial($serial)
    {
        // Get part details with latest status where status = 'Out'
        $result = DB::selectOne("
            WITH combined AS (
                SELECT serial_no, item_part_id, location_id, quantity, status, created_at
                FROM receiving
                WHERE serial_no = ? AND serial_no IS NOT NULL
                UNION ALL
                SELECT serial_no, NULL, NULL, NULL, status, created_at
                FROM part_requests
                WHERE serial_no = ?
            ),
            ranked AS (
                SELECT *,
                       ROW_NUMBER() OVER (PARTITION BY serial_no ORDER BY created_at DESC) as rn
                FROM combined
            )
            SELECT serial_no, item_part_id, location_id, quantity, status
            FROM ranked
            WHERE rn = 1 AND status = 'Out'
        ", [$serial, $serial]);

        if ($result && $result->item_part_id) {
            $itemPart = ItemPart::find($result->item_part_id);
            $location = Location::find($result->location_id);

            return response()->json([
                'part_no' => $itemPart->part_no,
                'description' => $itemPart->description,
                'quantity' => $result->quantity,
                'location' => $location ? $location->code : null,
                'location_id' => $result->location_id,
            ]);
        }

        return response()->json([
            'part_no' => null,
            'description' => null,
            'quantity' => null,
            'location' => null,
            'location_id' => null,
        ]);
    }

    public function getAllParts()
    {
        // Get all item_parts
        $parts = ItemPart::all();
        return response()->json(['parts' => $parts]);
    }

    public function getSerialsByItemPart($itemPartId)
    {
        // Get serials with their latest status (max created_at from both receiving and part_requests)
        // Show only serials where latest status = 'Out'
        $requests = DB::select("
            WITH combined AS (
                SELECT serial_no, item_part_id, location_id, quantity, status, created_at
                FROM receiving
                WHERE item_part_id = ? AND serial_no IS NOT NULL
                UNION ALL
                SELECT pr.serial_no, r.item_part_id, r.location_id, r.quantity, pr.status, pr.created_at
                FROM part_requests pr
                INNER JOIN receiving r ON pr.serial_no = r.serial_no
                WHERE r.item_part_id = ? AND pr.serial_no IS NOT NULL
            ),
            ranked AS (
                SELECT *,
                       ROW_NUMBER() OVER (PARTITION BY serial_no ORDER BY created_at DESC) as rn
                FROM combined
            )
            SELECT serial_no, item_part_id, location_id, quantity, status, created_at
            FROM ranked
            WHERE rn = 1 AND status = 'Out'
            ORDER BY created_at DESC
        ", [$itemPartId, $itemPartId]);

        $serials = [];
        foreach ($requests as $req) {
            $location = Location::find($req->location_id);
            $serials[] = [
                'serial_no' => $req->serial_no,
                'quantity' => $req->quantity,
                'location' => $location ? $location->code : null,
                'location_id' => $req->location_id,
            ];
        }

        return response()->json(['serials' => $serials]);
    }

    public function getReturnableMachines()
    {
        // Get machines with latest status = 'Out' (sent out, need to be returned)
        $machines = Machine::where('status', 'Out')
            ->select('id', 'serial_no', 'description', 'category_id', 'location_id', 'status')
            ->get();

        $result = [];
        foreach ($machines as $machine) {
            $category = Category::find($machine->category_id);
            $location = Location::find($machine->location_id);

            $result[] = [
                'id' => $machine->id,
                'serial_no' => $machine->serial_no,
                'description' => $machine->description,
                'category' => $category ? $category->code : null,
                'category_id' => $machine->category_id,
                'location' => $location ? $location->code : null,
                'location_id' => $machine->location_id,
            ];
        }

        return response()->json(['machines' => $result]);
    }

    public function getReturnablePartsWithSerials()
    {
        // Get serials with their latest status (max created_at from both receiving and part_requests)
        // Show only serials where latest status = 'Out'
        $requests = DB::select("
            WITH combined AS (
                SELECT serial_no, item_part_id, location_id, quantity, status, created_at
                FROM receiving
                WHERE serial_no IS NOT NULL
                UNION ALL
                SELECT pr.serial_no, r.item_part_id, r.location_id, r.quantity, pr.status, pr.created_at
                FROM part_requests pr
                INNER JOIN receiving r ON pr.serial_no = r.serial_no
                WHERE pr.serial_no IS NOT NULL
            ),
            ranked AS (
                SELECT *,
                       ROW_NUMBER() OVER (PARTITION BY serial_no ORDER BY created_at DESC) as rn
                FROM combined
            )
            SELECT serial_no, item_part_id, location_id, quantity, status, created_at
            FROM ranked
            WHERE rn = 1 AND status = 'Out'
            ORDER BY created_at DESC
        ");

        $data = [];
        foreach ($requests as $req) {
            $itemPart = ItemPart::find($req->item_part_id);
            $location = Location::find($req->location_id);

            $partNo = $itemPart->part_no;
            if (!isset($data[$partNo])) {
                $data[$partNo] = [
                    'part_no' => $partNo,
                    'description' => $itemPart->description,
                    'item_part_id' => $req->item_part_id,
                    'serials' => [],
                ];
            }

            $data[$partNo]['serials'][] = [
                'serial_no' => $req->serial_no,
                'quantity' => $req->quantity,
                'location' => $location ? $location->code : null,
                'location_id' => $req->location_id,
            ];
        }

        return response()->json(['parts' => array_values($data)]);
    }

    public function editPartsLocation()
    {
        $receivings = DB::table('receiving')
            ->join('item_parts', 'receiving.item_part_id', '=', 'item_parts.id')
            ->leftJoin('locations', 'receiving.location_id', '=', 'locations.id')
            ->select(
                'receiving.id',
                'receiving.serial_no',
                'receiving.quantity',
                'receiving.status',
                'receiving.location_id',
                'item_parts.part_no',
                'item_parts.description',
                'locations.code as location_code',
                'locations.description as location_name'
            )
            ->orderBy('item_parts.description')
            ->get();

        $locations = Location::where('is_active', true)->orderBy('code')->get();

        return view('inventory.locations.edit-parts', compact('receivings', 'locations'));
    }

    public function updatePartsLocation(Request $request)
    {
        $request->validate([
            'updates'                => 'required|array',
            'updates.*.receiving_id' => 'required|exists:receiving,id',
            'updates.*.location_id'  => 'required|exists:locations,id',
        ]);

        foreach ($request->updates as $update) {
            DB::table('receiving')
                ->where('id', $update['receiving_id'])
                ->update([
                    'location_id'         => $update['location_id'],
                    'location_updated_at' => now(),
                    'location_updated_by' => auth()->user()->name,
                ]);
        }

        return redirect()->route('inventory.edit-parts-location')
            ->with('success', 'Parts locations updated successfully.');
    }

    public function getMachineAvailableParts($machineId)
    {
        $parts = MachinePart::with('itemPart')
            ->where('machine_id', $machineId)
            ->where('status', 'available')
            ->get()
            ->map(function ($part) {
                return [
                    'machine_part_id' => $part->id,
                    'item_part_id' => $part->item_part_id,
                    'part_no' => $part->itemPart->part_no ?? '-',
                    'description' => $part->itemPart->description ?? '-',
                    'quantity' => $part->quantity,
                    'serial_no' => $part->serial_no,
                ];
            });

        return response()->json(['parts' => $parts]);
    }
}
