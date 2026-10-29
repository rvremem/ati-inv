<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ItemPart;
use App\Models\Location;
use App\Models\Customer;
use App\Models\Category;
use App\Models\Machine;
use App\Models\MachinePart;
use App\Models\Supplier;
use App\Models\Receiving;
use App\Models\Hub;
use App\Http\Controllers\Concerns\ImportsCsv;
use App\Http\Controllers\Concerns\ScopesByHub;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class InventoryController extends Controller
{
    use ImportsCsv;
    use ScopesByHub;

    /**
     * Batch upload columns for the parts catalogue, in template order.
     *
     * customer is deliberately absent: the Add Item form shows a Client select
     * but itemStore() never persists it (item_parts has no customer_id — the
     * link lives in the customer_item_parts pivot), so a column here would be
     * silently dropped.
     */
    /**
     * Second-file columns: stock for the parts in the first file, serialised
     * or not, in one sheet.
     *
     * Mirrors the machines/machine-parts pair — a parent file and a child file
     * linked by a natural key rather than a database id, so both are things a
     * person can type in a spreadsheet. Here the key is part_no.
     *
     * Which of serial_no / quantity a row uses is decided by the part's own
     * is_serialized flag, not by the sheet: see importItemStockRows().
     *
     * There is no date_received or supplier column: stock is received today,
     * from the supplier the part itself names in item_parts.
     */
    public const ITEM_STOCK_BATCH_COLUMNS = [
        'part_no',
        'ati_po',
        'client',
        'hub',
        'serial_no',
        'quantity',
        'location',
        'remarks',
    ];

    /**
     * The only statuses a stock batch upload row is booked in with. Not a CSV
     * column: see batchStockStatus().
     */
    public const ITEM_SERIAL_BATCH_STATUSES = ['Receiving', 'Old Stock'];

    /** The client a stock row is booked against when its client cell is blank. */
    public const BATCH_DEFAULT_CLIENT = 'Automated Technologies Inc.';

    /**
     * What a serialised row puts in serial_no when the unit has none. Each one
     * is stored numbered (N/A-1, N/A-2, …) so every unit still has a serial of
     * its own.
     */
    public const BATCH_NO_SERIAL = 'N/A';

    /**
     * What a serial in the stock file may contain: letters, digits, and
     * / - . + (two serials joined by / or ., or vendor formats such as
     * 465824T+1341C40524). Anything else — spaces, brackets, colons — is a
     * note typed into the cell, e.g. "(SN:08GAPSTZ" or "…loc:R2BL1)", and the
     * row is skipped rather than saved with a serial nobody can search for.
     */
    public const BATCH_SERIAL_PATTERN = '/^[A-Za-z0-9\/\-.+]+$/';

    /**
     * A serial Excel has turned into a rounded number, e.g. 2.4E+17. The real
     * digits are gone, so the row is skipped.
     */
    public const BATCH_MANGLED_SERIAL_PATTERN = '/^\d+(\.\d+)?E\+\d+$/i';

    public const ITEM_BATCH_COLUMNS = [
        'part_no',
        'description',
        'manufacturer',
        'model',
        'type',
        'supplier',
        'unit_cost',
        'min_stock',
        'is_serialized',
        'remarks',
        'is_active',
        'warranty'
    ];

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

    /**
     * Unit cost is visible to Super Admin and Developer only.
     *
     * isSA() already covers both. Everyone else can manage the catalogue
     * without seeing what anything cost — pricing is commercially sensitive
     * in a way a part number is not.
     */
    private function canSeeCost(): bool
    {
        return (bool) auth()->user()?->isSA();
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
            // Only active suppliers are offered on the form; a part already
            // pointing at a retired one keeps it until someone edits the part.
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'canManage' => $this->canManageItems(),
            'canSeeCost' => $this->canSeeCost(),
        ]);
    }

    public function itemsData(Request $request)
    {
        // Server-side DataTables endpoint: search + order + paginate in SQL,
        // returning only the current page of rows. Each row is sent as the full
        // model array because the edit modal is populated straight from it.
        // Index positions match the view's <th> order.
        // Positional, and the view drops the Unit Cost column for anyone who
        // may not see it — so this list has to drop it too, or every index
        // after it would sort by the wrong field.
        $orderColumns = $this->canSeeCost()
            ? ['part_no', 'description', 'manufacturer', 'type', 'suppliers.name', 'unit_cost', 'is_active']
            : ['part_no', 'description', 'manufacturer', 'type', 'suppliers.name', 'is_active'];

        // Matches the previous ItemPart::get() - soft-deleted rows are excluded
        // by the model, but inactive ones still show with an "Inactive" badge.
        // The supplier name is joined in rather than eager-loaded so it can be
        // sorted and searched in SQL alongside the item's own columns.
        $base = ItemPart::query()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'item_parts.supplier_id')
            ->select('item_parts.*', 'suppliers.name as supplier_name');

        // Unfiltered total (before the search box is applied).
        $recordsTotal = ItemPart::count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                // Qualified because the suppliers join puts a second `name`
                // column in scope and an unqualified column would be ambiguous.
                foreach (['part_no', 'description', 'manufacturer', 'model', 'type'] as $col) {
                    $q->orWhere("item_parts.{$col}", 'like', "%{$search}%");
                }
                $q->orWhere('suppliers.name', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $base)->count();

        $orderIdx = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        if ($orderIdx !== null && isset($orderColumns[$orderIdx])) {
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

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            // Stripped server-side, not hidden in CSS: the row payload is also
            // what fills the edit modal, so leaving it in would put the cost in
            // the page source for someone not allowed to read it.
            'data' => $base->get()->map(function ($item) {
                $row = $item->toArray();

                if (! $this->canSeeCost()) {
                    unset($row['unit_cost']);
                }

                return $row;
            }),
        ]);
    }

    public function itemCreate()
    {
        $this->authorizeItemManagement();

        return view('inventory.items.create', ['canSeeCost' => $this->canSeeCost()]);
    }

    public function itemStore(Request $request)
    {
        $this->authorizeItemManagement();

        $validated = $request->validate([
            'part_no' => 'required|unique:item_parts',
            'description' => 'required',
            'manufacturer' => 'nullable',
            'model' => 'nullable',
            'warranty' => 'nullable',
            'supplier_id' => 'nullable|exists:suppliers,id',
            // Only required of someone who can see the field. For everyone
            // else the form does not render it, so demanding it would make
            // the catalogue unusable for them.
            'unit_cost' => $this->canSeeCost() ? 'required|numeric' : 'nullable|numeric',
            'min_stock' => 'required|integer',
            'type' => 'required',
            'is_serialized' => 'boolean',
            'remarks' => 'nullable',
        ]);

        // Never taken from the request when it is not theirs to set - a
        // posted unit_cost from a hidden field would otherwise stick.
        if (! $this->canSeeCost()) {
            $validated['unit_cost'] = 0;
        }

        // "— None —" posts an empty string. ConvertEmptyStringsToNull already
        // handles that for real requests, but the FK rejects '' outright, so it
        // is not something to leave depending on middleware order.
        $validated['supplier_id'] = ($validated['supplier_id'] ?? null) ?: null;

        $item = ItemPart::create($validated);

        // No quantity here on purpose: this creates a catalogue entry, which
        // holds no stock. Stock only exists once something is received. No date
        // either — it would just repeat the log row's own Date & Time column.
        ActivityLog::log('Added New Item', "Item No: {$item->part_no}");
        return redirect()->route('inventory.items')->with('success', 'Item created successfully.');
    }

    /**
     * The stock half of the upload: one file for both kinds of part, told
     * apart by the part's own is_serialized flag.
     *
     * - Serialised part: each row is one physical unit, so quantity is always
     *   1 whatever the column says. A serial_no of N/A is stored numbered —
     *   N/A-1, N/A-2, … — continuing past the highest already on file, since
     *   serials are kept unique across every part.
     * - Non-serialised part: a blank quantity counts as 1. Rows for the same
     *   part going to the same place (hub, location, PO, client) are summed
     *   into a single receipt, so two bare rows make qty 2. serial_no is
     *   blank or N/A.
     *
     * Required on every row: part_no, hub, location, and serial_no for a
     * serialised part. Each receipt becomes a receiving row the way the Receive Parts
     * screen would create it, dated today, with the part's own supplier from
     * item_parts, status Receiving when the row has a PO and Old Stock when it
     * does not (see batchStockStatus()). A blank client is booked against
     * BATCH_DEFAULT_CLIENT.
     *
     * A part already in the catalogue is a valid target: unlike machines, an
     * existing part has an id to attach stock to.
     *
     * A location that is not on file is created, in the hub the row names (or
     * the restricted user's own). Creation happens after every other check
     * that can skip the row, so a skipped row never leaves a stray location
     * behind.
     *
     * @param  array<string, array{id:int, is_serialized:bool, supplier_id:?int}>  $newParts  keyed by lower-cased part_no
     * A part not marked serialised that the file gives real serials for is
     * switched to serialised. A location code already used at another hub is
     * prefixed with the row's hub (CEBU-R2AL1). A client cell listing several
     * clients goes to BATCH_DEFAULT_CLIENT with the list kept in remarks.
     *
     * @return array{serials:int, quantity_lines:int, quantity_units:int, skipped:int, reasons:array, created_locations:array<int,string>, created_customers:array<int,string>, switched_parts:array<int,string>}
     */
    private function importItemStockRows(UploadedFile $file, array $newParts): array
    {
        [$header, $records] = $this->readCsv($file);

        // Every location, active or not, with its hub kept alongside. locations.code
        // is unique, so an inactive match has to be recognised here rather than
        // re-created. The hub is kept so a row naming a shelf that belongs to
        // another branch is still caught instead of silently filing stock in the
        // wrong place. New locations are put back in this map, so a code that
        // appears on several rows creates one location, not one per row.
        $locations = Location::get()
            ->mapWithKeys(fn($l) => [mb_strtolower(trim($l->code)) => [
                'id' => $l->id,
                'hub_id' => $l->hub_id,
                'is_active' => (bool) $l->is_active,
            ]]);

        $customers = Customer::get()
            ->mapWithKeys(fn($c) => [mb_strtolower(trim($c->name)) => $c->id]);

        $hubRows = Hub::get();
        $hubs = $hubRows->mapWithKeys(fn($h) => [mb_strtolower(trim($h->hub_name)) => $h->id]);
        // For the hub prefix a clashing location code gets, e.g. CEBU-R2AL1.
        $hubPrefixes = $hubRows->mapWithKeys(fn($h) => [
            $h->id => mb_strtoupper(preg_replace('/\s+/', '', trim($h->hub_name))),
        ]);

        // Serials already booked in. A serial identifies one physical unit, so
        // re-importing one would double-count that unit in Stock Levels.
        $onFile = DB::table('receiving')->whereNotNull('serial_no')
            ->pluck('serial_no')->map(fn($x) => mb_strtolower(trim((string) $x)))->flip();

        $noSerialKey = mb_strtolower(self::BATCH_NO_SERIAL);

        // Parts the sheet gives a real serial on at least one row. A part not
        // yet marked serialised is switched over when first reached (see
        // below), so the decision holds for all of its rows whatever their
        // order in the file.
        $serialisedInFile = [];
        foreach ($records as $row) {
            $data = $this->rowToPayload($header, $row, self::ITEM_STOCK_BATCH_COLUMNS);
            $serialKey = mb_strtolower(trim($data['serial_no'] ?? ''));

            // Only a serial that will be accepted counts; a messy one is
            // skipped below and must not switch the part on its own.
            $serialRaw = trim($data['serial_no'] ?? '');

            if ($data !== null && $serialKey !== '' && $serialKey !== $noSerialKey
                && preg_match(self::BATCH_SERIAL_PATTERN, $serialRaw)
                && ! preg_match(self::BATCH_MANGLED_SERIAL_PATTERN, $serialRaw)) {
                $serialisedInFile[mb_strtolower(trim($data['part_no']))] = true;
            }
        }

        // Resolved parts, so each is looked up (and possibly switched) once.
        $partCache = $newParts;

        // Next free N/A-n. The loop at the point of use also steps over any
        // number already taken, in case a gap was filled by hand.
        $naNext = (int) DB::table('receiving')->where('serial_no', 'like', self::BATCH_NO_SERIAL . '-%')
            ->pluck('serial_no')
            ->map(fn($s) => (int) substr((string) $s, strlen(self::BATCH_NO_SERIAL) + 1))
            ->max() + 1;

        // A restricted user files everything against their own branch whatever
        // the sheet says, the same clamp the Receive Parts form applies.
        $forcedHub = $this->hubScope();

        $seen = [];
        $groups = [];
        $serials = 0;
        $skipped = 0;
        $reasons = [];
        $createdLocations = [];
        $createdCustomers = [];
        $switchedParts = [];

        foreach ($records as $i => $row) {
            $line = $i + 2;
            $data = $this->rowToPayload($header, $row, self::ITEM_STOCK_BATCH_COLUMNS);

            if ($data === null) {
                $skipped++;
                $reasons[] = "line {$line}: column count does not match the header";
                continue;
            }

            $partNo = trim($data['part_no'] ?? '');

            if ($partNo === '') {
                $skipped++;
                $reasons[] = "line {$line}: part_no is required";
                continue;
            }

            $partKey = mb_strtolower($partNo);
            $label = "{$partNo} (line {$line})";

            // A part from this upload first, then the existing catalogue.
            $part = $partCache[$partKey] ?? null;

            if (! $part) {
                $existing = ItemPart::whereRaw('LOWER(part_no) = ?', [$partKey])
                    ->select('id', 'is_serialized', 'supplier_id')->first();

                if (! $existing) {
                    $skipped++;
                    $reasons[] = "{$label}: no part '{$partNo}' in the items file or the catalogue";
                    continue;
                }

                $part = [
                    'id' => $existing->id,
                    'is_serialized' => (bool) $existing->is_serialized,
                    'supplier_id' => $existing->supplier_id,
                ];
            }

            // The sheet has serials for this part, so it is serialised: the
            // catalogue flag is switched to match rather than the rows refused.
            if (! $part['is_serialized'] && isset($serialisedInFile[$partKey])) {
                ItemPart::whereKey($part['id'])->update(['is_serialized' => true]);

                ActivityLog::log(
                    'Updated Item',
                    "Item No: {$partNo} | marked serialised by an item stock import (the file lists serials for it)"
                );

                $part['is_serialized'] = true;
                $switchedParts[] = $partNo;
            }

            $partCache[$partKey] = $part;

            $serial = trim($data['serial_no'] ?? '');
            $serialKey = mb_strtolower($serial);
            $noSerial = $serialKey === $noSerialKey;
            // One serial is one unit; a non-serialised row may override below.
            $quantity = 1;

            if ($part['is_serialized']) {
                if ($serial === '') {
                    $skipped++;
                    $reasons[] = "{$label}: serial_no is required for a serialised part — use "
                        . self::BATCH_NO_SERIAL . ' if the unit has none';
                    continue;
                }

                if (! $noSerial) {
                    if (preg_match(self::BATCH_MANGLED_SERIAL_PATTERN, $serial)) {
                        $skipped++;
                        $reasons[] = "{$label}: serial_no '{$serial}' was rounded by Excel — re-enter it with the column formatted as Text";
                        continue;
                    }

                    if (! preg_match(self::BATCH_SERIAL_PATTERN, $serial)) {
                        $skipped++;
                        $reasons[] = "{$label}: serial_no '{$serial}' contains notes or spaces — only letters, digits and / - . + are allowed";
                        continue;
                    }

                    $label = $serial;

                    if (isset($seen[$serialKey])) {
                        $skipped++;
                        $reasons[] = "{$serial}: listed twice in the file";
                        continue;
                    }

                    if ($onFile->has($serialKey)) {
                        $skipped++;
                        $reasons[] = "{$serial}: already received — a serial is one physical unit";
                        continue;
                    }
                }
            } else {
                // A valid serial would have switched the part to serialised
                // above, so one still here is messy: skipped like any other.
                // N/A is ignored.
                if ($serial !== '' && ! $noSerial) {
                    $skipped++;
                    $reasons[] = "{$label}: serial_no '{$serial}' contains notes or spaces, or was rounded by Excel";
                    continue;
                }

                // Blank counts as 1, so two bare rows of a part make qty 2.
                $qtyRaw = trim($data['quantity'] ?? '');

                if ($qtyRaw !== '') {
                    if (! ctype_digit($qtyRaw) || (int) $qtyRaw < 1) {
                        $skipped++;
                        $reasons[] = "{$label}: quantity '{$qtyRaw}' must be a whole number greater than zero";
                        continue;
                    }

                    $quantity = (int) $qtyRaw;
                }
            }

            $status = $this->batchStockStatus($data);

            // --- hub -------------------------------------------------------
            // Required, because stock has to land in a branch, the location
            // is checked against it below, and it is where a location that is
            // not on file gets created.
            $hubName = trim($data['hub'] ?? '');
            $hubId = $forcedHub;

            if ($hubId === null) {
                if ($hubName === '') {
                    $skipped++;
                    $reasons[] = "{$label}: hub is required";
                    continue;
                }

                $hubId = $hubs->get(mb_strtolower($hubName));

                if (! $hubId) {
                    $skipped++;
                    $reasons[] = "{$label}: hub '{$hubName}' is not on file";
                    continue;
                }
            }

            // --- location (lookup only) -------------------------------------
            // A location that is not on file is NOT a reason to skip: it is
            // created further down, once nothing else can reject the row.
            $locationCode = trim($data['location'] ?? '');

            if ($locationCode === '') {
                $skipped++;
                $reasons[] = "{$label}: location is required";
                continue;
            }

            // "R2AL4 & SMART MIXED L4" or "R2BL1/R2BL3" names two shelves; a
            // unit sits on one, so the row is skipped rather than a combined
            // location created.
            if (preg_match('/[&\/]/', $locationCode)) {
                $skipped++;
                $reasons[] = "{$label}: location '{$locationCode}' names more than one location — use one";
                continue;
            }

            $locationKey = mb_strtolower($locationCode);
            $location = $locations->get($locationKey);

            // locations.code is unique across every hub, so the same shelf
            // name used at another branch cannot be created twice. The row's
            // hub is put in front instead — R2AL1 at Cebu becomes CEBU-R2AL1 —
            // which keeps the stock at the branch the row names.
            if ($location && (int) $location['hub_id'] !== (int) $hubId) {
                $locationCode = ($hubPrefixes[$hubId] ?? $hubId) . '-' . $locationCode;
                $locationKey = mb_strtolower($locationCode);
                $location = $locations->get($locationKey);
            }

            if ($location) {
                if (! $location['is_active']) {
                    $skipped++;
                    $reasons[] = "{$label}: location '{$locationCode}' exists but is inactive — reactivate it on the Locations page first";
                    continue;
                }

                // Only reachable if even the hub-prefixed code belongs to
                // another branch, i.e. someone created it there by hand.
                if ((int) $location['hub_id'] !== (int) $hubId) {
                    $skipped++;
                    $reasons[] = "{$label}: location '{$locationCode}' is not in that hub";
                    continue;
                }
            }

            // Not columns: stock is received today, from the part's own supplier.
            $date = now()->toDateString();
            $supplierId = $part['supplier_id'];

            // --- client ----------------------------------------------------
            // A blank client is booked against BATCH_DEFAULT_CLIENT. A name
            // that is not on file is created rather than rejected, the same
            // trade-off as the items file's supplier: a typo becomes a new
            // entry to merge by hand. Placed after every check that can skip
            // the row, so a skipped row never leaves a stray client behind.
            // A cell listing several clients ("A Inc.,B Corp.") cannot be one
            // client on a receiving row: it goes to the default client, and
            // the list is kept in remarks so it is not lost.
            $client = trim($data['client'] ?? '') ?: self::BATCH_DEFAULT_CLIENT;
            $remarks = ($data['remarks'] ?? '') ?: null;

            if (str_contains($client, ',')) {
                $remarks = ($remarks !== null ? "{$remarks}; " : '') . "Clients: {$client}";
                $client = self::BATCH_DEFAULT_CLIENT;
            }

            try {
                $customerId = $this->findOrCreateByName(
                    Customer::class, $client, $customers, 'an item stock import', $createdCustomers
                );
            } catch (\Throwable $e) {
                $skipped++;
                $reasons[] = "{$label}: could not create client — {$e->getMessage()}";
                continue;
            }

            // --- location (create) -----------------------------------------
            // Last, so a row rejected by any check above leaves nothing behind.
            if (! $location) {
                try {
                    $newLocation = Location::create([
                        'code'        => $locationCode,
                        // description is required on the Locations form; the
                        // code is the best label available. Edit it later on
                        // the Locations page.
                        'description' => $locationCode,
                        'hub_id'      => $hubId,
                        'is_active'   => true,
                        'created_by'  => auth()->id(),
                    ]);
                } catch (\Throwable $e) {
                    $skipped++;
                    $reasons[] = "{$label}: could not create location '{$locationCode}' — {$e->getMessage()}";
                    continue;
                }

                ActivityLog::log(
                    'Created Location',
                    "Code: {$newLocation->code} | auto-created by an item stock import"
                );

                $location = ['id' => $newLocation->id, 'hub_id' => $hubId, 'is_active' => true];
                $locations->put($locationKey, $location);
                $createdLocations[] = $newLocation->code;
            }

            $atiPo = ($data['ati_po'] ?? '') ?: null;

            // Non-serialised: held back and summed with any other row bound for
            // the same receipt; written once the whole file has been read.
            if (! $part['is_serialized']) {
                // Date and supplier are fixed per part, so they need no place here.
                $groupKey = implode('|', [
                    $part['id'], $hubId, $location['id'], mb_strtolower((string) $atiPo), $customerId,
                ]);

                $groups[$groupKey] ??= [
                    'label' => $label,
                    'rows' => 0,
                    'remarks' => [],
                    'attributes' => [
                        'date_received' => $date,
                        'ati_po' => $atiPo,
                        'customer_id' => $customerId,
                        'supplier_id' => $supplierId,
                        'item_part_id' => $part['id'],
                        'location_id' => $location['id'],
                        'hub_id' => $hubId,
                        'quantity' => 0,
                        'serial_no' => null,
                        'status' => $status,
                        'created_by' => auth()->id(),
                    ],
                ];

                $groups[$groupKey]['attributes']['quantity'] += $quantity;
                $groups[$groupKey]['rows']++;

                if ($remarks !== null) {
                    $groups[$groupKey]['remarks'][$remarks] = true;
                }

                continue;
            }

            if ($noSerial) {
                while ($onFile->has($noSerialKey . '-' . $naNext)) {
                    $naNext++;
                }

                $serial = self::BATCH_NO_SERIAL . '-' . $naNext++;
                $serialKey = mb_strtolower($serial);
            }

            try {
                Receiving::create([
                    'date_received' => $date,
                    'ati_po' => $atiPo,
                    'customer_id' => $customerId,
                    'supplier_id' => $supplierId,
                    'item_part_id' => $part['id'],
                    'location_id' => $location['id'],
                    'hub_id' => $hubId,
                    // One serial is one unit; see ReceivingController::serialisedFlags().
                    'quantity' => 1,
                    'serial_no' => $serial,
                    'return_remarks' => $remarks,
                    'status' => $status,
                    'created_by' => auth()->id(),
                ]);

                $seen[$serialKey] = true;
                $serials++;
            } catch (\Throwable $e) {
                $skipped++;
                $reasons[] = "{$label}: {$e->getMessage()}";
            }
        }

        // One receipt per group. Distinct remarks from the summed rows are
        // kept, joined, so none is lost in the merge.
        $quantityLines = 0;
        $quantityUnits = 0;
        $quantityRows = 0;

        foreach ($groups as $group) {
            try {
                Receiving::create($group['attributes'] + [
                    'return_remarks' => implode('; ', array_keys($group['remarks'])) ?: null,
                ]);

                $quantityLines++;
                $quantityUnits += $group['attributes']['quantity'];
                $quantityRows += $group['rows'];
            } catch (\Throwable $e) {
                $skipped += $group['rows'];
                $reasons[] = "{$group['label']}: {$e->getMessage()}";
            }
        }

        return [
            'serials' => $serials,
            'quantity_lines' => $quantityLines,
            'quantity_units' => $quantityUnits,
            // File rows behind those lines, for the summary's row count.
            'quantity_rows' => $quantityRows,
            'skipped' => $skipped,
            'reasons' => $reasons,
            'created_locations' => $createdLocations,
            'created_customers' => $createdCustomers,
            'switched_parts' => $switchedParts,
        ];
    }

    /**
     * The status a serials or quantities row is booked in with, decided by its
     * ati_po: a PO means stock arriving on an order now (Receiving); no PO
     * means stock already on hand being backfilled (Old Stock).
     *
     * A status column in an older file is ignored — rowToPayload() drops
     * columns not in the list.
     */
    private function batchStockStatus(array $data): string
    {
        return trim($data['ati_po'] ?? '') !== '' ? 'Receiving' : 'Old Stock';
    }

    /**
     * Resolves a client or supplier name to an id, creating it when it is not
     * on file. Blank means "none" and returns null.
     *
     * $map holds every row, active or not, keyed by lower-cased name, so a
     * deactivated name resolves to that row instead of creating a second one.
     * It is updated in place so a new name on several rows is created once.
     *
     * @param  class-string<Customer|Supplier>  $model
     * @param  array<int,string>                $created  names created so far; appended to
     */
    private function findOrCreateByName(string $model, string $name, Collection $map, string $source, array &$created): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $key = mb_strtolower($name);

        if ($id = $map->get($key)) {
            return $id;
        }

        $row = $model::create(['name' => $name, 'is_active' => true]);

        ActivityLog::log(
            $model === Customer::class ? 'Created Customer' : 'Created Supplier',
            "Name: {$row->name} | auto-created by {$source}"
        );

        $map->put($key, $row->id);
        $created[] = $row->name;

        return $row->id;
    }

    public function itemEdit(ItemPart $item)
    {
        $this->authorizeItemManagement();

        return view('inventory.items.edit', ['item' => $item, 'canSeeCost' => $this->canSeeCost()]);
    }

    public function itemUpdate(Request $request, ItemPart $item)
    {
        $this->authorizeItemManagement();

        $validated = $request->validate([
            'part_no' => "required|unique:item_parts,part_no,{$item->id}",
            'description' => 'required',
            'manufacturer' => 'nullable',
            'model' => 'nullable',
            // Listed here or it is dropped: only validated keys reach update().
            'warranty' => 'nullable',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'unit_cost' => $this->canSeeCost() ? 'required|numeric' : 'nullable|numeric',
            'min_stock' => 'required|integer',
            'type' => 'required',
            'is_serialized' => 'boolean',
            'remarks' => 'nullable',
            'is_active' => 'boolean',
        ]);

        // Dropped rather than defaulted: on an edit the part already has a
        // cost, and someone who cannot see it must not be able to overwrite
        // it - blind, or by posting the field by hand.
        if (! $this->canSeeCost()) {
            unset($validated['unit_cost']);
        }

        $validated['is_serialized'] = $request->boolean('is_serialized');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['supplier_id'] = ($validated['supplier_id'] ?? null) ?: null;

        $item->update($validated);
        ActivityLog::log('Updated Item', "Part No: {$item->part_no} | {$item->description}");
        return redirect()->route('inventory.items')->with('success', 'Item updated successfully.');
    }

    public function itemDelete(ItemPart $item)
    {
        $this->authorizeItemManagement();

        ActivityLog::log(
            'Deleted Item',
            "Part No: {$item->part_no} | {$item->description}"
        );

        $item->is_active = 0;
        $item->save();

        $item->delete();

        return redirect()
            ->route('inventory.items')
            ->with('success', 'Item deleted successfully.');
    }

    public function itemBatchUpload()
    {
        $this->authorizeItemManagement();

        return view('inventory.items.batch-upload', [
            'columns' => self::ITEM_BATCH_COLUMNS,
            'sampleRows' => $this->itemSampleRows(),
            'stockColumns' => self::ITEM_STOCK_BATCH_COLUMNS,
            'stockRows' => $this->itemStockSampleRows(),
        ]);
    }

    private function itemStockSampleRows(): array
    {
        // Real names throughout, so the template's spelling resolves as
        // written. The location is one that belongs to the sample hub —
        // the importer refuses a shelf at another branch.
        // REPLACE-WITH-PART-NO-1 is the serialised part in the items template
        // and -2 the non-serialised one, so the two line up when uploaded
        // together.
        $customer = Customer::where('is_active', true)->orderBy('name')->value('name') ?: '';

        $location = Location::where('is_active', true)->orderBy('code')->first();
        $hubName = $location?->hub?->hub_name
            ?? Hub::orderBy('hub_name')->value('hub_name')
            ?? '';
        $locationCode = $location->code ?? '';

        return [
            // Serialised with a PO, so booked in as Receiving. quantity is
            // left blank: a serialised row is always 1.
            ['REPLACE-WITH-PART-NO-1', 'PO-0001', $customer, $hubName, 'REPLACE-WITH-SERIAL-1', '', $locationCode, ''],
            // Serialised with no serial, stored as N/A-1, N/A-2, … Blank
            // client, so the default client. No PO, so Old Stock.
            ['REPLACE-WITH-PART-NO-1', '', '', $hubName, self::BATCH_NO_SERIAL, '', $locationCode, 'Optional note'],
            // Non-serialised: these two rows are summed into one receipt of 15.
            ['REPLACE-WITH-PART-NO-2', 'PO-0001', $customer, $hubName, '', '10', $locationCode, ''],
            ['REPLACE-WITH-PART-NO-2', 'PO-0001', $customer, $hubName, '', '5', $locationCode, ''],
        ];
    }

    public function itemStockSampleCsv()
    {
        $this->authorizeItemManagement();

        return $this->streamCsv(
            self::ITEM_STOCK_BATCH_COLUMNS,
            $this->itemStockSampleRows(),
            'item_stock_template.csv'
        );
    }

    public function itemSampleCsv()
    {
        $this->authorizeItemManagement();

        return $this->streamCsv(self::ITEM_BATCH_COLUMNS, $this->itemSampleRows(), 'item_parts_template.csv');
    }

    /**
     * Type is copied off a part already on file so the template demonstrates a
     * spelling the catalogue actually uses — it is free text, so nothing
     * validates it and a house style is easy to break by accident.
     *
     * The part numbers stay obvious placeholders: this upload creates records,
     * so uploading the template unedited makes two junk parts, named clearly
     * enough to spot and delete.
     */
    private function itemSampleRows(): array
    {
        $type = ItemPart::where('is_active', true)->orderBy('part_no')->value('type') ?: 'Hardware';
        // A supplier name really on file, so the template's spelling resolves.
        $supplier = Supplier::where('is_active', true)->orderBy('name')->value('name') ?: '';

        return [
            ['REPLACE-WITH-PART-NO-1', 'Sample part', 'ACME', 'MODEL-A', $type, $supplier, '1500.00', '5', '1', '', '1', '1 Month'],
            // Second row leaves supplier blank, showing that it is optional.
            ['REPLACE-WITH-PART-NO-2', 'Another part', '', '', $type, '', '0', '0', '0', 'Optional note', '1', '1 Month'],
        ];
    }

    /**
     * Up to two files, the same parent/child shape the machines upload uses.
     *
     * The catalogue file creates parts; the optional stock file books in units
     * against them — one per serial for serialised parts, a summed quantity for
     * the rest. Both run in one transaction so a failure part-way cannot leave
     * parts created with half their stock.
     */
    public function itemBatchStore(Request $request)
    {
        $this->authorizeItemManagement();

        $request->validate([
            'csv_file'   => 'nullable|file|mimes:csv,txt',
            'stock_file' => 'nullable|file|mimes:csv,txt',
        ]);

        if (! $request->hasFile('csv_file') && ! $request->hasFile('stock_file')) {
            return back()->withInput()->withErrors([
                'csv_file' => 'Upload at least one file — item parts, item stock, or both.',
            ]);
        }

        $itemsFile = $request->file('csv_file');
        $stockFile = $request->file('stock_file');

        [$items, $stock] = DB::transaction(function () use ($itemsFile, $stockFile) {
            $items = $itemsFile
                ? $this->importItemRows($itemsFile)
                : ['imported' => 0, 'skipped' => 0, 'reasons' => [], 'created_suppliers' => 0, 'parts' => []];

            $stock = $stockFile
                ? $this->importItemStockRows($stockFile, $items['parts'])
                : [
                    'serials' => 0, 'quantity_lines' => 0, 'quantity_units' => 0, 'quantity_rows' => 0,
                    'skipped' => 0, 'reasons' => [],
                    'created_locations' => [], 'created_customers' => [], 'switched_parts' => [],
                ];

            return [$items, $stock];
        });

        $createdLocations = $stock['created_locations'];
        $createdCustomers = $stock['created_customers'];
        // Only the items file creates suppliers; stock takes the part's own.
        $createdSuppliers = $items['created_suppliers'];

        $skipped = $items['skipped'] + $stock['skipped'];

        ActivityLog::log(
            'Batch Uploaded Items',
            "{$items['imported']} item(s) imported, {$stock['serials']} serial(s) received, "
                . "{$stock['quantity_lines']} quantity line(s) received ({$stock['quantity_units']} unit(s)), "
                . "{$skipped} row(s) skipped, "
                . "{$createdSuppliers} supplier(s) created, "
                . count($createdCustomers) . ' client(s) created, '
                . count($createdLocations) . ' location(s) created'
        );

        // Kept short on purpose: what came in, split by kind, and which rows
        // did not. New clients, locations and parts switched to serialised
        // are each in the activity log, so they are left out here.
        $rows = $stock['serials'] + $stock['quantity_rows'];

        $message = ($items['imported'] > 0 ? number_format($items['imported']) . ' part(s) added. ' : '')
            . 'Imported ' . number_format($rows) . ' row(s): '
            . number_format($stock['serials']) . ' serialised, '
            . number_format($stock['quantity_rows']) . ' not serialised.';

        if ($skipped > 0) {
            // Each reason starts with the row's serial or part number (e.g.
            // "5BXRA0D4D6Y0BX: listed twice…"); only that part is shown.
            $labels = array_map(
                fn($reason) => explode(': ', $reason, 2)[0],
                array_merge($items['reasons'], $stock['reasons'])
            );
            $shown = array_slice($labels, 0, 20);

            $message .= ' Skipped ' . number_format($skipped) . ': ' . implode(', ', $shown)
                . (count($labels) > count($shown) ? ', and ' . (count($labels) - count($shown)) . ' more' : '')
                . '.';
        }

        return redirect()->route('inventory.items')->with('success', $message);
    }

    /**
     * The catalogue half of the upload.
     *
     * Returns the parts it created keyed by lower-cased part_no, which is how
     * the serials file finds what to attach to — the same way the machine parts
     * import is handed the serials of the machines just created.
     */
    private function importItemRows(UploadedFile $file): array
    {
        [$header, $records] = $this->readCsv($file);

        // Matches the Add Item form's `unique:item_parts` rule, which is not
        // soft-delete aware: letting a trashed part_no back in here would create
        // a row the form itself would refuse to.
        $onFile = ItemPart::withTrashed()->pluck('part_no')
            ->map(fn($p) => mb_strtolower(trim((string) $p)))->flip();

        // Matched by name because the CSV carries no database ids, same as the
        // machines import. Every supplier is in the map, not just the active
        // ones: a name that is on file but deactivated must resolve to that row
        // rather than fall through and create a second one under the same name.
        $suppliers = Supplier::get()
            ->mapWithKeys(fn($s) => [mb_strtolower(trim($s->name)) => $s->id]);

        $seen = [];
        $parts = [];
        $imported = 0;
        $skipped = 0;
        $createdSuppliers = 0;
        $reasons = [];

        foreach ($records as $i => $row) {
            $line = $i + 2; // +1 for the header, +1 because humans count from 1
            $data = $this->rowToPayload($header, $row, self::ITEM_BATCH_COLUMNS);

            if ($data === null) {
                $skipped++;
                $reasons[] = "line {$line}: column count does not match the header";
                continue;
            }

            $partNo = $data['part_no'];
            $key = mb_strtolower($partNo);

            if ($partNo === '' || $data['description'] === '' || $data['type'] === '') {
                $skipped++;
                $reasons[] = "line {$line}: part_no, description and type are required";
                continue;
            }

            if ($onFile->has($key)) {
                $skipped++;
                $reasons[] = "{$partNo}: already in the catalogue — an import never edits an existing part";
                continue;
            }

            if (isset($seen[$key])) {
                $skipped++;
                $reasons[] = "{$partNo}: listed twice in the file";
                continue;
            }

            // Blank falls back to the column default rather than skipping the
            // row; a value that is there but not a number is a typo worth
            // reporting instead of quietly importing as zero.
            if ($data['unit_cost'] !== '' && ! is_numeric($data['unit_cost'])) {
                $skipped++;
                $reasons[] = "{$partNo}: unit_cost '{$data['unit_cost']}' is not a number";
                continue;
            }

            if ($data['min_stock'] !== '' && ! ctype_digit($data['min_stock'])) {
                $skipped++;
                $reasons[] = "{$partNo}: min_stock '{$data['min_stock']}' is not a whole number";
                continue;
            }

            // Blank means "no supplier", which is allowed — the column is
            // nullable. A name that is not on file is created rather than
            // rejected, so an import never stalls on a supplier nobody has
            // entered yet. Note the trade-off: a misspelt name is indis-
            // tinguishable from a genuinely new one, so typos become new
            // suppliers to be merged by hand on the Suppliers page.
            $supplierId = null;
            if ($data['supplier'] !== '') {
                $supplierKey = mb_strtolower($data['supplier']);
                $supplierId = $suppliers->get($supplierKey);

                if (! $supplierId) {
                    try {
                        $supplier = Supplier::create(['name' => $data['supplier'], 'is_active' => true]);
                    } catch (\Throwable $e) {
                        $skipped++;
                        $reasons[] = "{$partNo}: could not create supplier '{$data['supplier']}' — {$e->getMessage()}";
                        continue;
                    }

                    ActivityLog::log(
                        'Created Supplier',
                        "Name: {$supplier->name} | auto-created by an item parts import"
                    );

                    // Cached so a name appearing on several rows creates one
                    // supplier, not one per row.
                    $suppliers->put($supplierKey, $supplier->id);
                    $supplierId = $supplier->id;
                    $createdSuppliers++;
                }
            }

            try {
                $created = ItemPart::create([
                    'part_no' => $partNo,
                    'description' => $data['description'],
                    'manufacturer' => $data['manufacturer'] ?: null,
                    'model' => $data['model'] ?: null,
                    'type' => $data['type'],
                    'supplier_id' => $supplierId,
                    // Ignored for anyone who may not see costs, so a CSV column
                    // cannot be used to set a figure they are not allowed to
                    // read back. The importer still accepts the column so the
                    // shared template works for everybody.
                    'unit_cost' => $this->canSeeCost() && $data['unit_cost'] !== '' ? $data['unit_cost'] : 0,
                    'min_stock' => $data['min_stock'] === '' ? 0 : (int) $data['min_stock'],
                    'is_serialized' => $this->csvBool($data['is_serialized'], false),
                    'remarks' => $data['remarks'] ?: null,
                    'is_active' => $this->csvBool($data['is_active'], true),
                    'warranty' => $data['warranty'] ?: null,
                ]);
                // Kept so the serials file can resolve part_no to an id, and
                // so is_serialized is known without re-querying: a serial
                // against a non-serialised part is refused below.
                $seen[$key] = true;
                $parts[$key] = [
                    'id' => $created->id,
                    'is_serialized' => (bool) $created->is_serialized,
                    // The stock file receives from this supplier.
                    'supplier_id' => $created->supplier_id,
                ];
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $reasons[] = "{$partNo}: {$e->getMessage()}";
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'created_suppliers' => $createdSuppliers,
            'reasons' => $reasons,
            'parts' => $parts,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Suppliers
    |--------------------------------------------------------------------------
    | Supplier is a two-value classification rather than a directory of vendors
    | — see the seed migration that puts "Local Supplier" and "Foreign Supplier"
    | on file. That is why this page carries only a name and an active flag: the
    | address / contact_no columns exist on the table but nothing populates or
    | reads them.
    |
    | Note there is an older, unlinked Suppliers CRUD under Settings that edits
    | the same table and still exposes those two columns.
    */

    public function suppliers()
    {
        $this->authorizeSupplierManagement();

        $suppliers = Supplier::orderBy('name')->paginate(15);

        return view('inventory.suppliers.index', compact('suppliers'));
    }

    public function supplierStore(Request $request)
    {
        $this->authorizeSupplierManagement();

        $validated = $request->validate([
            'name' => 'required|unique:suppliers',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $supplier = Supplier::create($validated);
        ActivityLog::log('Created Supplier', "Name: {$supplier->name}");

        return redirect()->route('inventory.suppliers')->with('success', 'Supplier created successfully.');
    }

    public function supplierUpdate(Request $request, Supplier $supplier)
    {
        $this->authorizeSupplierManagement();

        $validated = $request->validate([
            'name' => "required|unique:suppliers,name,{$supplier->id}",
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $supplier->update($validated);
        ActivityLog::log('Updated Supplier', "Name: {$supplier->name}");

        return redirect()->route('inventory.suppliers')->with('success', 'Supplier updated successfully.');
    }

    /**
     * Deletes only when nothing points at the supplier.
     *
     * suppliers has no soft delete, and item_parts / machines / receiving all
     * carry a nullable FK to it — so a hard delete would silently blank the
     * supplier on every row that referenced it. Blocking is the honest
     * behaviour; deactivating is the way to retire one that is in use.
     */
    public function supplierDelete(Supplier $supplier)
    {
        $this->authorizeSupplierManagement();

        $inUse = [
            'item part' => $supplier->itemParts()->count(),
            'machine' => $supplier->machines()->count(),
            'receiving row' => $supplier->receiving()->count(),
        ];

        $blocking = array_filter($inUse);
        if ($blocking !== []) {
            $detail = implode(', ', array_map(
                fn($label, $count) => "{$count} {$label}" . ($count === 1 ? '' : 's'),
                array_keys($blocking),
                $blocking
            ));

            return redirect()->route('inventory.suppliers')->with(
                'error',
                "{$supplier->name} is still used by {$detail}. Set it to Inactive instead of deleting it."
            );
        }

        ActivityLog::log('Deleted Supplier', "Name: {$supplier->name}");
        $supplier->delete();

        return redirect()->route('inventory.suppliers')->with('success', 'Supplier deleted successfully.');
    }

    /** Same rule as item parts: everyone except Engineers. */
    private function authorizeSupplierManagement(): void
    {
        abort_unless($this->canManageItems(), 403, 'You are not allowed to manage suppliers.');
    }

    /*
    |--------------------------------------------------------------------------
    | Parts Location
    |--------------------------------------------------------------------------
    | Where each part number physically sits, and how much of it is there. One
    | row per (part, location) that still has stock, with an action to move a
    | whole bucket to another location — singly or by CSV.
    */

    /**
     * The receiving statuses that mean the stock is physically on the shelf.
     *
     * 'Old Stock' is a status a serial batch upload can write (see
     * ITEM_SERIAL_BATCH_STATUSES) to backfill a unit that was already on
     * hand before it was entered into the system — it is not a pending
     * request and it has not left the building, so it belongs here
     * alongside a fresh 'Receiving' and a 'Return'. Stock Levels still
     * breaks it out into its own column (see stocksData()) so the
     * distinction from a new receipt stays visible, but every on-hand
     * figure driven by this list — Stock Levels' total, Parts Location,
     * the move screen, available-serial lookups — treats it as on hand.
     */
    public const ON_HAND_STATUSES = ['Receiving', 'Return', 'Old Stock'];

    /**
     * Batch-move CSV columns. Not table fields — this upload moves stock rather
     * than inserting it, so a row names the bucket and where it should go.
     */
    /**
     * Location import columns, in template order.
     *
     * hub is matched by name rather than id, like every other import here, and
     * is required: the receiving screens offer locations by hub, so one loaded
     * without a hub would exist but never appear in a dropdown.
     */
    public const LOCATION_BATCH_COLUMNS = ['code', 'description', 'hub', 'is_active'];

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
    /**
     * On-hand per part per location, in units.
     *
     * Both halves sum `quantity` rather than counting rows. Counting rows is
     * only right for serialised stock, which is one unit per row; an
     * unserialised row carries its whole quantity, so a receipt of 5 was
     * reported as 1 here and on Stock Levels alike.
     *
     * onHandAt() reads this same SQL, so the figure the move screen shows and
     * the figure it validates against cannot disagree.
     *
     * This filters `status IN (self::ON_HAND_STATUSES)`, which is
     * 'Receiving', 'Return' and 'Old Stock' — a row a serial batch upload
     * wrote as 'Old Stock' (see ITEM_SERIAL_BATCH_STATUSES /
     * importItemStockRows()) counts here exactly like a fresh receipt,
     * since it represents stock that was already on the shelf, just
     * entered into the system later than a normal receiving. Stock Levels
     * still reports it under its own "Old Stock" column (see
     * stocksData()) so the distinction stays visible there.
     */
    private function onHandByLocationSql(?int $filterHubId = null): string
    {
        $avail = "'" . implode("','", self::ON_HAND_STATUSES) . "'";

        // Hub limit, inlined because this is a raw statement with no bindings
        // of its own. The value is an int straight from User::hubScope(), never
        // user input, and is cast again here so it cannot be anything else.
        //
        // receiving carries hub_id directly. part_requests does not, so a
        // request is attributed to the hub of whoever raised it — the same rule
        // Stock Levels uses, so the two screens cannot disagree.
        // The enforced scope first, then the hub the screen is showing. Both
        // are ints from trusted sources — hubScope() and hubFilterId(), which
        // already refuses a hub the reader may not see — so neither can carry
        // user input into the statement.
        $hubId = $this->hubScope() ?? $filterHubId;

        $recvHub = $hubId === null ? '' : ' AND hub_id = ' . (int) $hubId;
        $reqHub = $hubId === null ? '' : ' AND requested_by IN (SELECT id FROM users WHERE hub_id = ' . (int) $hubId . ')';

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
                                SELECT item_part_id, ISNULL(location_id, 0) AS location_id, SUM(quantity) AS qty
                                FROM receiving
                                WHERE status IN ($avail)$recvHub
                                GROUP BY item_part_id, ISNULL(location_id, 0)
                            ) i
                            FULL OUTER JOIN (
                                SELECT item_part_id, ISNULL(location_id, 0) AS location_id, SUM(quantity) AS qty
                                FROM part_requests
                                WHERE status = 'Out'$reqHub
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

        // Same shape as Stock Levels: nothing is shown until a hub is
        // picked, because stock only means something at a branch.
        return view('inventory.parts-location.index', [
            'locations' => $locations,
            'hubs' => $this->selectableHubs(),
        ]);
    }

    public function partsLocationData(Request $request)
    {
        // No hub, no rows — the same guard Stock Levels uses. Returning
        // everything instead would present a company-wide list under a
        // heading that says one branch.
        $hubId = $this->hubFilterId($request);

        if ($hubId === null && $this->hubScope() === null) {
            return response()->json([
                'draw' => (int) $request->input('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        $base = DB::table(DB::raw('(' . $this->onHandByLocationSql($hubId) . ') AS pl'))
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
            : DB::table(DB::raw('(' . $this->onHandByLocationSql($hubId) . ') AS pl'))
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
        $data = $base->get()->map(fn($row) => [
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

        return array_map(fn($row) => $row->id, $rows);
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
        return $this->streamCsv(self::PARTS_LOCATION_BATCH_COLUMNS, $this->partsLocationSampleRows(), 'parts_location_template.csv');
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

        $samples = array_map(fn($r) => [$r->part_no, $r->code, 'NEW-LOCATION-CODE'], $rows);

        return count($samples) === 2 ? $samples : [
            ['PART-001', 'HO', 'NEW-LOCATION-CODE'],
            ['PART-002', 'CEBU', 'NEW-LOCATION-CODE'],
        ];
    }

    /**
     * Uses the shared readCsv()/rowToPayload() helpers, same as the item and
     * item-serial importers — a hand-rolled str_getcsv()/array_combine() parse
     * (the previous implementation) has no BOM handling, so a file re-saved by
     * Excel as "CSV UTF-8" silently breaks the very first header cell and every
     * row reports its required columns as missing. Routing through the trait
     * gets this parser the same robustness for free and keeps every importer
     * in this controller behaving the same way.
     */
    public function partsLocationBatchStore(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt']);

        [$header, $records] = $this->readCsv($request->file('csv_file'));

        $activeLocations = Location::where('is_active', true)->get()
            ->mapWithKeys(fn($l) => [mb_strtolower(trim($l->code)) => $l]);

        $moved = 0;
        $skipped = 0;
        $reasons = [];

        foreach ($records as $i => $row) {
            $line = $i + 2; // +1 for the header, +1 because humans count from 1
            $data = $this->rowToPayload($header, $row, self::PARTS_LOCATION_BATCH_COLUMNS);

            if ($data === null) {
                $skipped++;
                $reasons[] = "line {$line}: column count does not match the header";
                continue;
            }

            $partNo = trim($data['part_no'] ?? '');
            $fromCode = trim($data['old_location'] ?? '');
            $toCode = trim($data['new_location'] ?? '');

            if ($partNo === '' || $fromCode === '' || $toCode === '') {
                $skipped++;
                $reasons[] = "line {$line}: part_no, old_location and new_location are all required";
                continue;
            }

            $to = $activeLocations->get(mb_strtolower($toCode));
            if (! $to) {
                $skipped++;
                $reasons[] = "{$partNo}: {$toCode} is not an active location";
                continue;
            }

            $part = ItemPart::whereRaw('LOWER(part_no) = ?', [mb_strtolower($partNo)])->first();
            $from = Location::whereRaw('LOWER(code) = ?', [mb_strtolower($fromCode)])->first();

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

    /**
     * Forces a restricted user's own hub onto a location they are saving.
     *
     * The form only offers hubs they may see, but hub_id is a posted value —
     * without this, editing the request would create a shelf in a branch they
     * cannot even view.
     */
    private function clampLocationHub(array &$validated): void
    {
        if (($allowed = $this->hubScope()) !== null) {
            $validated['hub_id'] = $allowed;
        }
    }
    public function locations()
    {
        $locations = Location::with('hub')->orderBy('code')->get();

        return view('inventory.locations.index', [
            'locations' => $locations,
            'hubs' => $this->selectableHubs(),
        ]);
    }

    public function locationCreate()
    {
        // Only hubs this user may assign; a branch user cannot create a
        // shelf in someone else's site.
        return view('inventory.locations.create', ['hubs' => $this->selectableHubs()]);
    }

    public function locationStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|unique:locations',
            'description' => 'required',
            // Required: the receiving screens offer locations by hub, so one
            // with no hub would exist but be unusable.
            'hub_id' => 'required|exists:hub,id',
        ]);

        $this->clampLocationHub($validated);

        $validated['created_by'] = auth()->id();
        $location = Location::create($validated);
        ActivityLog::log('Created Location', "Code: {$location->code} | {$location->description}");
        return redirect()->route('inventory.locations')->with('success', 'Location created successfully.');
    }

    public function locationEdit(Location $location)
    {
        return view('inventory.locations.edit', [
            'location' => $location,
            'hubs' => $this->selectableHubs(),
        ]);
    }

    public function locationUpdate(Request $request, Location $location)
    {
        $validated = $request->validate([
            'code' => "required|unique:locations,code,{$location->id}",
            'description' => 'required',
            'hub_id' => 'required|exists:hub,id',
        ]);

        $this->clampLocationHub($validated);

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

    private function locationSampleRows(): array
    {
        // Real hub names, so the template's spelling resolves as written.
        $hubs = Hub::orderBy('hub_name')->pluck('hub_name');
        $first = $hubs->first() ?: 'Manila';
        $second = $hubs->skip(1)->first() ?: $first;

        return [
            ['REPLACE-WITH-CODE-1', 'Sample location', $first, '1'],
            ['REPLACE-WITH-CODE-2', 'Another location', $second, '1'],
        ];
    }

    public function locationSampleCsv()
    {
        return $this->streamCsv(
            self::LOCATION_BATCH_COLUMNS,
            $this->locationSampleRows(),
            'locations_template.csv'
        );
    }

    public function locationBatchUpload()
    {
        return view('inventory.locations.batch-upload', [
            'columns' => self::LOCATION_BATCH_COLUMNS,
            'sampleRows' => $this->locationSampleRows(),
        ]);
    }

    /**
     * Uses the shared readCsv()/rowToPayload() helpers, same as the item and
     * item-serial importers, instead of hand-parsing with str_getcsv() and
     * array_combine().
     *
     * That hand-rolled parse is what was causing "code and description are
     * required" on every row even when the file clearly had both filled in:
     * array_combine($header, $row) keys strictly on the header text, and a
     * file re-saved by Excel as "CSV UTF-8" prepends a BOM (byte-order mark)
     * to the very first header cell. trim() does not strip a BOM, so the
     * header silently became "\xEF\xBB\xBFcode" instead of "code" — every
     * row's $data['code'] lookup missed, and every row was reported as
     * missing code (and often description too). readCsv()/rowToPayload()
     * are the same helpers the item/serial importers already rely on and
     * don't have this problem, so routing this importer through them fixes
     * it and keeps every batch upload in this controller behaving the same
     * way.
     */
    public function locationBatchStore(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        [$header, $records] = $this->readCsv($request->file('csv_file'));

        // Matched by name because the CSV carries no database ids. Every hub is
        // in the map; a name that is not on file is reported rather than
        // created, since hubs are a fixed short list, not free text.
        $hubs = Hub::get()->mapWithKeys(fn($h) => [mb_strtolower(trim($h->hub_name)) => $h->id]);

        // A restricted user may only load locations into their own branch.
        $forcedHub = $this->hubScope();

        $imported = 0;
        $skipped = 0;
        $reasons = [];
        $seen = [];

        foreach ($records as $i => $row) {
            $line = $i + 2; // +1 for the header, +1 because humans count from 1
            $data = $this->rowToPayload($header, $row, self::LOCATION_BATCH_COLUMNS);

            if ($data === null) {
                $skipped++;
                $reasons[] = "line {$line}: column count does not match the header";
                continue;
            }

            $code = trim($data['code'] ?? '');
            $description = trim($data['description'] ?? '');

            if ($code === '' || $description === '') {
                $skipped++;
                $reasons[] = "line {$line}: code and description are required";
                continue;
            }

            $key = mb_strtolower($code);

            if (isset($seen[$key])) {
                $skipped++;
                $reasons[] = "{$code}: listed twice in the file";
                continue;
            }

            if (Location::whereRaw('LOWER(code) = ?', [$key])->exists()) {
                $skipped++;
                $reasons[] = "{$code}: already on file — an import never edits an existing location";
                continue;
            }

            // Required, so an import cannot create a shelf that no receiving
            // screen will ever offer.
            $hubName = trim($data['hub'] ?? '');
            $hubId = $forcedHub;

            if ($hubId === null) {
                if ($hubName === '') {
                    $skipped++;
                    $reasons[] = "{$code}: hub is required";
                    continue;
                }

                $hubId = $hubs->get(mb_strtolower($hubName));

                if (! $hubId) {
                    $skipped++;
                    $reasons[] = "{$code}: hub '{$hubName}' is not on file";
                    continue;
                }
            }

            // Blank defaults to Active, matching the batch-upload page's own
            // documented behaviour. filter_var() on an empty string returns
            // false, not the default, so blank is checked for explicitly
            // rather than left to the filter_var() default value.
            $isActiveRaw = trim($data['is_active'] ?? '');
            $isActive = $isActiveRaw === '' ? true : filter_var($isActiveRaw, FILTER_VALIDATE_BOOLEAN);

            Location::create([
                'code' => $code,
                'description' => $description,
                'hub_id' => $hubId,
                'is_active' => $isActive,
                'created_by' => auth()->id(),
            ]);

            $seen[$key] = true;
            $imported++;
        }

        ActivityLog::log('Batch Uploaded Locations', "{$imported} location(s) imported, {$skipped} skipped");

        return redirect()->route('inventory.locations')
            ->with('success', $this->batchSummary('location', $imported, $skipped, $reasons));
    }

    /**
     * Limits Stock Levels to parts that have actually moved in this hub.
     *
     * A catalogue entry with nothing received against it and nothing ever
     * requested has no stock to report — it rendered as a row of zeros, which
     * says nothing and pushes the parts people are looking for further down
     * the table. Those entries are still on Item Parts, where the catalogue
     * belongs; this screen is about movement.
     *
     * Any part_requests row counts, not just status 'Out' the way the on-hand
     * sub-select does: a part sitting in a pending request has a history worth
     * seeing, even though none of it is out yet.
     *
     * Shared by the row query and the unfiltered count so the two cannot
     * disagree about which parts exist.
     */
    private function hasStockHistoryInHub($hubId): \Closure
    {
        return function ($query) use ($hubId) {
            $query->where(function ($q) use ($hubId) {
                $q->whereExists(fn($sub) => $sub->select(DB::raw(1))
                    ->from('receiving')
                    ->whereColumn('receiving.item_part_id', 'item_parts.id')
                    ->where('receiving.hub_id', $hubId))
                    ->orWhereExists(fn($sub) => $sub->select(DB::raw(1))
                        ->from('part_requests')
                        ->join('users', 'users.id', '=', 'part_requests.requested_by')
                        ->whereColumn('part_requests.item_part_id', 'item_parts.id')
                        ->where('users.hub_id', $hubId));
            });
        };
    }

    public function stocks()
    {
        // Rows are loaded via server-side DataTables (see stocksData) so the page
        // shell renders instantly instead of running the per-item stock queries
        // for the whole catalogue up front.
        //
        // No hub is preselected: stock is a per-branch figure, and defaulting to
        // one would show numbers that look like the whole company's.
        // A restricted user is offered only their own hub, so the picker
        // cannot even suggest a branch they are not allowed to open.
        return view('inventory.stocks', [
            'hubs' => $this->selectableHubs(),
        ]);
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
            'old_qty',
            'receiving_qty',
            'return_qty',
            'out_qty',
            'total_qty',
        ];

        // Stock is a per-hub figure, so the screen is empty until one is picked.
        // Returning nothing (rather than everything) is the safe default: a
        // company-wide total presented under a hub heading would be read as
        // that hub's stock.
        // Validated as a strict integer, not cast. A cast would turn junk like
        // "1 OR 1=1" into hub 1 and quietly serve that hub's figures; passing
        // it through instead reaches the driver, which rejects the type and
        // throws a 500. Neither is a SQL injection — the value is bound as a
        // parameter — but both are wrong answers to a malformed request.
        $hubId = filter_var($request->input('hub_id'), FILTER_VALIDATE_INT);

        // Enforced here as well as in the picker: the endpoint takes the hub
        // from the query string, so a restricted user could otherwise read
        // another branch simply by editing the URL.
        $allowed = $this->hubScope();
        if ($allowed !== null && $hubId !== $allowed) {
            $hubId = false;
        }

        if (! $hubId || $hubId <= 0 || ! Hub::whereKey($hubId)->exists()) {
            return response()->json([
                'draw' => (int) $request->input('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'onHandTotal' => 0,
            ]);
        }

        // Receiving carries the hub, so it is what places stock in one.
        //
        // Sums `quantity`, not rows. A serialised part is always one unit per
        // row, so counting rows happened to be right for those — but an
        // unserialised row carries its whole quantity, and counting it as 1
        // reported 3 units of a part as 1. Summing the column is correct for
        // both, because serialised rows are forced to quantity 1 on the way in.
        $received = DB::table('receiving')
            ->select(
                'item_part_id',
                DB::raw("SUM(CASE WHEN status = 'Receiving' THEN quantity ELSE 0 END) as recv_qty"),
                DB::raw("SUM(CASE WHEN status = 'Return' THEN quantity ELSE 0 END) as ret_qty"),
                DB::raw("SUM(CASE WHEN status = 'Old Stock' THEN quantity ELSE 0 END) as old_qty")
            )
            ->where('hub_id', $hubId)
            ->groupBy('item_part_id');

        // part_requests has no hub of its own, so a request is attributed to
        // the hub of whoever raised it. A request from a user with no hub set
        // is counted against no hub at all, rather than against every one.
        $out = DB::table('part_requests')
            ->join('users', 'users.id', '=', 'part_requests.requested_by')
            // Units, not rows — same reason as the receiving sum above. One
            // request line for 5 unserialised pieces takes 5 off the shelf.
            ->select('part_requests.item_part_id', DB::raw('SUM(part_requests.quantity) as out_qty'))
            ->where('part_requests.status', 'Out')
            ->where('users.hub_id', $hubId)
            ->groupBy('part_requests.item_part_id');

        $base = ItemPart::query()
            ->tap($this->hasStockHistoryInHub($hubId))
            ->leftJoinSub($received, 'r', fn($join) => $join->on('r.item_part_id', '=', 'item_parts.id'))
            ->leftJoinSub($out, 'o', fn($join) => $join->on('o.item_part_id', '=', 'item_parts.id'))
            ->select(
                'item_parts.id',
                'item_parts.part_no',
                'item_parts.description',
                'item_parts.type',
                'item_parts.unit_cost',
                DB::raw('COALESCE(r.recv_qty, 0) as receiving_qty'),
                DB::raw('COALESCE(r.ret_qty, 0) as return_qty'),
                DB::raw('COALESCE(r.old_qty, 0) as old_qty'),
                DB::raw('COALESCE(o.out_qty, 0) as out_qty'),
                // On-hand is receiving + return + old stock - out. Old Stock
                // is real on-hand inventory (see ON_HAND_STATUSES) that just
                // entered the system later than a fresh receipt, so it adds
                // to the total the same as the other two.
                DB::raw('(COALESCE(r.recv_qty, 0) + COALESCE(r.ret_qty, 0) + COALESCE(r.old_qty, 0) - COALESCE(o.out_qty, 0)) as total_qty')
            );

        // Unfiltered total (before the search box is applied) - but still only
        // the parts this screen shows, or DataTables' "showing x of y" would
        // count rows nobody can reach.
        $recordsTotal = ItemPart::query()->tap($this->hasStockHistoryInHub($hubId))->count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                foreach (['part_no', 'description', 'type'] as $col) {
                    $q->orWhere("item_parts.{$col}", 'like', "%{$search}%");
                }
            });
        }

        $recordsFiltered = (clone $base)->count();

        // Totals for the header, summed across the whole hub rather than the
        // page on screen — a figure that changed when you clicked "next" would
        // be worse than none at all. Taken before ordering and pagination are
        // applied, so it covers every matching row.
        $onHandTotal = (int) (clone $base)->sum(DB::raw(
            '(COALESCE(r.recv_qty, 0) + COALESCE(r.ret_qty, 0) + COALESCE(r.old_qty, 0) - COALESCE(o.out_qty, 0))'
        ));

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

        $data = $base->get()->map(function ($item) use ($hubId) {
            return [
                'part_no' => $item->part_no,
                'description' => $item->description,
                'old_stock' => (int) $item->old_qty,
                'receiving_qty' => (int) $item->receiving_qty,
                'return_qty' => (int) $item->return_qty,
                'out_qty' => (int) $item->out_qty,
                'total_qty' => (int) $item->total_qty,
                'available_serials' => $this->availableSerialsFor($item->id, $hubId),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'onHandTotal' => $onHandTotal,
        ]);
    }

    /**
     * Serials whose latest status across receiving + part_requests is still
     * 'Receiving' or 'Return' — i.e. physically on hand at this hub.
     *
     * Only the receiving side is filtered by hub, because that is what puts a
     * serial in one. The request side is left unfiltered on purpose: once a
     * unit has been taken out it is gone from the shelf whoever took it, so
     * narrowing that half would show serials as available that are not.
     */
    private function availableSerialsFor($itemPartId, $hubId): array
    {
        $avail = "'" . implode("','", self::ON_HAND_STATUSES) . "'";

        $serials = DB::select("
            WITH combined AS (
                SELECT serial_no, status, created_at
                FROM receiving
                WHERE item_part_id = ? AND serial_no IS NOT NULL AND hub_id = ?
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
            WHERE rn = 1 AND status IN ($avail)
        ", [$itemPartId, $hubId]);

        return array_map(fn($s) => $s->serial_no, $serials);
    }

    /**
     * On-hand quantity for a non-serialised part, scoped to the caller's hub.
     *
     * Same arithmetic as stocksData()'s total_qty: everything received with
     * status Receiving/Return, minus everything requested out — so this can
     * never disagree with what Stock Levels shows for the part.
     */
    public function getPartOnHandQuantity($itemId)
    {
        $hubId = $this->hubScope();

        $received = DB::table('receiving')
            ->where('item_part_id', $itemId)
            ->whereIn('status', self::ON_HAND_STATUSES)
            ->when($hubId !== null, fn($q) => $q->where('hub_id', $hubId))
            ->sum('quantity');

        $out = DB::table('part_requests')
            ->join('users', 'users.id', '=', 'part_requests.requested_by')
            ->where('part_requests.item_part_id', $itemId)
            ->where('part_requests.status', 'Out')
            ->when($hubId !== null, fn($q) => $q->where('users.hub_id', $hubId))
            ->sum('part_requests.quantity');

        return response()->json([
            'available_quantity' => max(0, (int) $received - (int) $out),
        ]);
    }

    /**
     * SQL fragment limiting a `receiving` scan to the signed-in user's hub.
     *
     * The API endpoints below feed the part and serial dropdowns on the request
     * and return forms. They are raw statements, so the model global scope does
     * not reach them — without this a Cebu engineer was offered, and could
     * request, serials sitting in Manila.
     *
     * receiving is what decides: it carries hub_id, and it is where a serial
     * physically entered a branch. Inlined rather than bound because these
     * statements already use positional placeholders; the value comes from
     * User::hubScope(), never from the request, and is cast again here.
     */
    private function receivingHubClause(string $alias = ''): string
    {
        $hubId = $this->hubScope();

        if ($hubId === null) {
            return '';
        }

        $column = $alias === '' ? 'hub_id' : "{$alias}.hub_id";

        return " AND {$column} = " . (int) $hubId;
    }

    /**
     * The catalogue as this user may see it on a stock-facing form.
     *
     * Item Parts itself stays global — a part number is a definition, not
     * stock. But a request form offering a part that has never been in your
     * branch invites a request that cannot be filled, so those are narrowed to
     * parts with movement in the user's hub.
     */
    private function partsVisibleHere()
    {
        $query = ItemPart::query();
        $hubId = $this->hubScope();

        if ($hubId !== null) {
            $query->tap($this->hasStockHistoryInHub($hubId));
        }

        return $query;
    }

    public function getAvailableSerials($itemId)
    {
        // Get serials with their latest status (max created_at from both receiving and part_requests)
        // Show only serials where latest status = 'Receiving' or 'Return'
        $hub = $this->receivingHubClause();
        $avail = "'" . implode("','", self::ON_HAND_STATUSES) . "'";

        $serials = DB::select("
            WITH combined AS (
                SELECT serial_no, item_part_id, location_id, quantity, status, created_at
                FROM receiving
                WHERE item_part_id = ? AND serial_no IS NOT NULL{$hub}
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
            WHERE rn = 1 AND status IN ($avail)
        ", [$itemId]);

        $serialNumbers = array_map(fn($s) => $s->serial_no, $serials);

        return response()->json([
            'serials' => $serialNumbers,
        ]);
    }

    public function getSerialLocation($serial)
    {
        // Find the location where this serial was received
        $receiving = $this->applyHubScope(DB::table('receiving'), 'receiving.hub_id')
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
        $receiving = $this->applyHubScope(DB::table('receiving'), 'receiving.hub_id')
            ->where('item_part_id', $itemId)
            ->whereIn('status', self::ON_HAND_STATUSES)
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
        $hub = $this->receivingHubClause();

        $serials = DB::select("
            WITH combined AS (
                SELECT serial_no, status, created_at
                FROM receiving
                WHERE serial_no IS NOT NULL{$hub}
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
        $hub = $this->receivingHubClause();

        $result = DB::selectOne("
            WITH combined AS (
                SELECT serial_no, item_part_id, location_id, quantity, status, created_at
                FROM receiving
                WHERE serial_no = ? AND serial_no IS NOT NULL{$hub}
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
        // Narrowed to parts with movement in the user's hub; see
        // partsVisibleHere(). Administrators still get the whole catalogue.
        $parts = $this->partsVisibleHere()->get();
        return response()->json(['parts' => $parts]);
    }

    public function getSerialsByItemPart($itemPartId)
    {
        // Get serials with their latest status (max created_at from both receiving and part_requests)
        // Show only serials where latest status = 'Out'
        $hub = $this->receivingHubClause();
        $hubR = $this->receivingHubClause('r');

        $requests = DB::select("
            WITH combined AS (
                SELECT serial_no, item_part_id, location_id, quantity, status, created_at
                FROM receiving
                WHERE item_part_id = ? AND serial_no IS NOT NULL{$hub}
                UNION ALL
                SELECT pr.serial_no, r.item_part_id, r.location_id, r.quantity, pr.status, pr.created_at
                FROM part_requests pr
                INNER JOIN receiving r ON pr.serial_no = r.serial_no
                WHERE r.item_part_id = ? AND pr.serial_no IS NOT NULL{$hubR}
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
        $hub = $this->receivingHubClause();
        $hubR = $this->receivingHubClause('r');

        $requests = DB::select("
            WITH combined AS (
                SELECT serial_no, item_part_id, location_id, quantity, status, created_at
                FROM receiving
                WHERE serial_no IS NOT NULL{$hub}
                UNION ALL
                SELECT pr.serial_no, r.item_part_id, r.location_id, r.quantity, pr.status, pr.created_at
                FROM part_requests pr
                INNER JOIN receiving r ON pr.serial_no = r.serial_no
                WHERE pr.serial_no IS NOT NULL{$hubR}
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
        $receivings = $this->applyHubScope(DB::table('receiving'), 'receiving.hub_id')
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
            // Re-checked per row: receiving_id arrives in the request body, so
            // validating it merely exists would let a restricted user relocate
            // another branch's stock by posting its id.
            $this->applyHubScope(DB::table('receiving'), 'receiving.hub_id')
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
        // machine_parts carries no hub, so the machine is the gate. Machine is
        // hub-scoped globally, so a machine in another branch simply is not
        // found and the caller gets nothing rather than its parts list.
        abort_unless(Machine::whereKey($machineId)->exists(), 404);

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