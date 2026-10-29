<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Machine;
use App\Models\MachinePart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Hub;
use App\Models\Location;
use App\Models\ItemPart;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MachineController extends Controller
{
    use \App\Http\Controllers\Concerns\ScopesByHub;

    /**
     * Batch-upload column lists. Neither file carries a database id: the import
     * resolves every label to one. That is the whole point of the two-file
     * shape — machine_parts.item_part_id is NOT NULL and cannot be typed into a
     * spreadsheet, so part_no is looked up in (or added to) the catalogue first,
     * and machine_serial_no is looked up in the machines file's own results.
     *
     * hub is optional and exists for one reason: a location that is not on file
     * is created, and a location cannot exist without a hub. A restricted user
     * never needs it — their own hub is used. Blank means DEFAULT_HUB_NAME.
     */
    public const MACHINE_BATCH_COLUMNS = [
        'serial_no', 'description', 'model', 'category', 'customer',
        'supplier', 'location', 'ati_po', 'date_received', 'unit_cost', 'hub',
    ];

    public const MACHINE_PART_BATCH_COLUMNS = [
        'machine_serial_no', 'part_no', 'part_description', 'part_type',
        'quantity', 'serial_no', 'location', 'purpose', 'remarks',
    ];

    /** Status a freshly received machine gets, matching store(). */
    private const IMPORT_MACHINE_STATUS = 'Receiving';

    /** Status an installed part gets, matching storePartForm(). */
    private const IMPORT_PART_STATUS = 'available';

    /**
     * Hub used when a row names none and the user is not limited to one.
     * Matched by hub_name, case-insensitively, like every other hub lookup.
     */
    private const DEFAULT_HUB_NAME = 'Manila';

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Same rule as Item Parts (InventoryController::canManageItems): every role
     * but Engineer. The upload creates categories, suppliers, customers,
     * locations and catalogue parts as a side effect, so it is gated at least
     * as tightly as the catalogue it writes into.
     */
    private function canBatchUpload(): bool
    {
        $user = auth()->user();

        return $user && ! $user->isEngineer();
    }

    /** Guards the routes — hiding the button alone leaves them open. */
    private function authorizeBatchUpload(): void
    {
        abort_unless($this->canBatchUpload(), 403, 'You are not allowed to batch upload machines.');
    }

    public function index()
    {
        // machines.status carries the request lifecycle (Pending/Out/Return), so
        // the old correlated-subquery join onto machine_requests is redundant.
        $machines = Machine::with('category', 'customer', 'location')
            ->orderBy('serial_no')
            ->get();

        return view('machines.index', [
            'machines'       => $machines,
            'canBatchUpload' => $this->canBatchUpload(),
        ]);
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        $hubs = $this->selectableHubs();
        return view('machines.create', compact('categories', 'customers', 'suppliers', 'locations', 'hubs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date_received' => 'required|date',
            'ati_po' => 'nullable',
            'serial_no' => 'required|unique:machines',
            'description' => 'required',
            'model' => 'nullable',
            'category_id' => 'nullable|exists:categories,id',
            'customer_id' => 'nullable|exists:customers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'location_id' => 'nullable|exists:locations,id',
            'hub_id' => 'nullable|exists:hub,id',
        ]);

        // A restricted user may only file records against their own hub. The
        // form already offers just that one, but the field is a posted value:
        // without this, editing the request would book stock into a branch
        // they cannot even see.
        if (($allowed = $this->hubScope()) !== null) {
            $validated['hub_id'] = $allowed;
        }
        $validated['created_by'] = auth()->id();
        $validated['status'] = 'Receiving';
        $machine = Machine::create($validated);
        // Machine counterpart of 'Added New Item' — the serial is the machine's
        // item number, and like the catalogue add it carries no quantity and no
        // date. location_id is nullable on the form, hence the fallback.
        ActivityLog::log('Added New Machine', sprintf(
            'Serial: %s | Location: %s',
            $machine->serial_no,
            $machine->location->code ?? '-'
        ));

        return redirect()->route('machines.index')->with('success', 'Machine created successfully.');
    }

    public function show(Machine $machine)
    {
        $machine->load(['category', 'customer', 'supplier', 'location',
            'parts' => fn($q) => $q->where('status', '!=', 'Out')->with('itemPart'),
        ]);
        return view('machines.show', compact('machine'));
    }

    public function edit(Machine $machine)
    {
        $categories = Category::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        $items = ItemPart::all();
        $machine->load('parts');
        return view('machines.edit', compact('machine', 'categories', 'customers', 'suppliers', 'locations', 'items'));
    }

    public function update(Request $request, Machine $machine)
    {
        $validated = $request->validate([
            'date_received' => 'required|date',
            'serial_no' => "required|unique:machines,serial_no,{$machine->id}",
            'description' => 'required',
            'model' => 'nullable',
            'category_id' => 'nullable|exists:categories,id',
            'customer_id' => 'nullable|exists:customers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'location_id' => 'nullable|exists:locations,id',
            'part_id' => 'required|array',
            'part_id.*' => 'exists:item_parts,id',
            'part_quantity' => 'required|array',
            'part_quantity.*' => 'required|integer|min:1',
            'part_remarks' => 'nullable|array',
        ]);

        // Update machine record (exclude parts data)
        $machineData = collect($validated)->except(['part_id', 'part_quantity', 'part_remarks'])->toArray();
        $machine->update($machineData);
        ActivityLog::log('Updated Machine', "Serial: {$machine->serial_no} | {$machine->description}");

        // Delete existing parts and create new ones
        $machine->parts()->delete();

        $parts = $validated['part_id'];
        $quantities = $validated['part_quantity'];
        $remarks = $validated['part_remarks'] ?? [];

        foreach ($parts as $index => $partId) {
            MachinePart::create([
                'machine_id' => $machine->id,
                'item_part_id' => $partId,
                'quantity' => $quantities[$index],
                'remarks' => $remarks[$index] ?? null,
                'assembled_by' => auth()->id(),
                'assembled_at' => now(),
            ]);
        }

        return redirect()->route('machines.index')->with('success', 'Machine updated successfully.');
    }

    public function destroy(Machine $machine)
    {
        ActivityLog::log('Deleted Machine', "Serial: {$machine->serial_no} | {$machine->description}");
        $machine->delete();
        return redirect()->route('machines.index')->with('success', 'Machine deleted successfully.');
    }

    public function addPart(Request $request, Machine $machine)
    {
        $items = ItemPart::all();
        $locations = Location::where('is_active', true)->get();
        $returnTo = $this->partFormReturn($request);
        return view('machines.add-part', compact('machine', 'items', 'locations', 'returnTo'));
    }

    /**
     * Where the Add Parts form goes back to: the machine's own page when opened
     * from there, else Receiving, which linked it first. Whitelisted rather than
     * taking a URL, so the field cannot be used as an open redirect.
     */
    private function partFormReturn(Request $request): string
    {
        return $request->input('return') === 'show' ? 'show' : 'receiving';
    }

    public function storePartForm(Request $request, Machine $machine)
    {
        $validated = $request->validate([
            'item_part_id' => 'required|array',
            'item_part_id.*' => 'required|exists:item_parts,id',
            'quantity' => 'required|array',
            'quantity.*' => 'required|integer|min:1',
            'serial_no' => 'nullable|array',
            'description' => 'nullable|array',
            'remarks' => 'nullable|array',
        ]);

        foreach ($validated['item_part_id'] as $index => $itemPartId) {
            $serial = $validated['serial_no'][$index] ?? null;

            $existing = $serial ? MachinePart::where('machine_id', $machine->id)
                ->where('item_part_id', $itemPartId)
                ->where('serial_no', $serial)
                ->where('status', 'Out')
                ->first() : null;

            if ($existing) {
                // Refitting a part that was previously pulled. The row is reused,
                // so the disassembly is cleared: the part is back on the machine
                // and reporting it as both fitted and removed would be wrong.
                $existing->update([
                    'status' => MachinePart::STATUS_ASSEMBLED,
                    'assembled_by' => auth()->id(),
                    'assembled_at' => now(),
                    'disassembled_by' => null,
                    'disassembled_at' => null,
                ]);
            } else {
                MachinePart::create([
                    'machine_id' => $machine->id,
                    'item_part_id' => $itemPartId,
                    'quantity' => (int)$validated['quantity'][$index],
                    'serial_no' => $serial,
                    'remarks' => $validated['remarks'][$index] ?? null,
                    'status' => MachinePart::STATUS_ASSEMBLED,
                    'assembled_by' => auth()->id(),
                    'assembled_at' => now(),
                ]);
            }
        }

        ActivityLog::log('Added Parts to Machine', "Machine: {$machine->serial_no}");

        $redirect = $this->partFormReturn($request) === 'show'
            ? redirect()->route('machines.show', $machine)
            : redirect()->route('receiving.machines');

        return $redirect->with('success', 'Parts added to machine successfully.');
    }

    public function removePart(MachinePart $part)
    {
        $machine = $part->machine;
        ActivityLog::log('Removed Part from Machine', "Machine: {$machine->serial_no} | Part ID: {$part->id}");
        $part->delete();
        return redirect()->route('machines.show', $machine)->with('success', 'Part removed from machine successfully.');
    }

    public function onhand()
    {
        $machines = Machine::where('status', 'In Stock')
            ->with('category', 'customer', 'location')
            ->get();
        return view('machines.onhand', compact('machines'));
    }

    // ===================== BATCH UPLOAD =====================

    public function batchUpload()
    {
        $this->authorizeBatchUpload();

        return view('machines.batch-upload', [
            'machineColumns' => self::MACHINE_BATCH_COLUMNS,
            'partColumns'    => self::MACHINE_PART_BATCH_COLUMNS,
            'machineRows'    => $this->machineSampleRows(),
            'partRows'       => $this->machinePartSampleRows(),
        ]);
    }

    public function machineTemplateCsv()
    {
        $this->authorizeBatchUpload();

        return $this->streamCsv(self::MACHINE_BATCH_COLUMNS, $this->machineSampleRows(), 'machines_template.csv');
    }

    public function machinePartTemplateCsv()
    {
        $this->authorizeBatchUpload();

        return $this->streamCsv(self::MACHINE_PART_BATCH_COLUMNS, $this->machinePartSampleRows(), 'machine_parts_template.csv');
    }

    /**
     * Sample rows quote lookup values that are really on file, so the spellings
     * are known-good. category is the one that matters most: machines.category_id
     * is NOT NULL, so a template naming a category nobody has produces a file
     * where every row skips.
     *
     * The hub is the one the sample location belongs to, so the row is coherent
     * as written.
     *
     * The serials stay obvious placeholders. Unlike the parts-location template
     * this upload *creates* records, so uploading it unedited makes two junk
     * machines — named clearly enough to spot and delete.
     */
    private function machineSampleRows(): array
    {
        $category = Category::where('is_active', true)->orderBy('code')->value('code') ?? 'CATEGORY-CODE';
        $customer = Customer::where('is_active', true)->orderBy('name')->value('name') ?? '';
        $supplier = Supplier::where('is_active', true)->orderBy('name')->value('name') ?? '';

        $location = Location::where('is_active', true)->orderBy('code')->first();
        $locationCode = $location->code ?? '';
        $hubName = $location?->hub?->hub_name
            ?? Hub::orderBy('hub_name')->value('hub_name')
            ?? '';

        $today = now()->toDateString();

        return [
            ['REPLACE-WITH-SERIAL-1', 'Sample machine', 'MODEL-A', $category, $customer, $supplier, $locationCode, 'PO-0001', $today, '15000.00', $hubName],
            ['REPLACE-WITH-SERIAL-2', 'Another machine', 'MODEL-B', $category, $customer, '', $locationCode, '', $today, '0', $hubName],
        ];
    }

    private function machinePartSampleRows(): array
    {
        $part     = ItemPart::where('is_active', true)->orderBy('part_no')->first();
        $location = Location::where('is_active', true)->orderBy('code')->value('code') ?? '';

        return [
            // First row uses a part already in the catalogue, second shows the
            // auto-create path, so the template demonstrates both.
            ['REPLACE-WITH-SERIAL-1', $part->part_no ?? 'PART-001', $part->description ?? 'Existing catalogue part', $part->type ?? 'Part', '2', 'PART-SERIAL-001', $location, 'Installed', ''],
            ['REPLACE-WITH-SERIAL-1', 'NEW-PART-002', 'Added to the catalogue on import', 'Part', '1', '', $location, 'Spare', ''],
            ['REPLACE-WITH-SERIAL-2', $part->part_no ?? 'PART-001', $part->description ?? 'Existing catalogue part', $part->type ?? 'Part', '4', '', '', 'Installed', ''],
        ];
    }

    private function streamCsv(array $columns, array $rows, string $filename)
    {
        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Returns [header, rows].
     *
     * The BOM strip is not optional here: Excel on Windows writes UTF-8 CSVs
     * with a byte-order mark, which lands on the first header cell and turns
     * "serial_no" into "\xEF\xBB\xBFserial_no". The first column is the machine
     * key in both files, so without this every single row skips as unkeyed.
     *
     * Rows whose column count doesn't match the header are handed back as-is —
     * the caller counts them as skipped, because padding them would mis-align
     * every value on the row.
     */
    private function readCsv(UploadedFile $file): array
    {
        $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $records = array_map(fn ($line) => str_getcsv($line, ',', '"', '\\'), $lines);
        $header = array_map('trim', array_shift($records) ?: []);

        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        return [$header, $records];
    }

    /**
     * Maps one CSV row onto the known columns, ignoring anything extra.
     *
     * Columns the file leaves out entirely come back as '' rather than absent,
     * so a file carrying only the required columns is read as blanks instead of
     * throwing undefined-key warnings on every optional field. That is also
     * what keeps an older machines.csv without the new hub column working.
     */
    private function rowToPayload(array $header, array $row, array $columns): ?array
    {
        if (count($row) !== count($header)) {
            return null;
        }

        $data = array_intersect_key(array_combine($header, $row), array_flip($columns));
        $data = array_map(fn ($v) => trim((string) $v), $data);

        return $data + array_fill_keys($columns, '');
    }

    /**
     * Resolves a location code to an id, creating the location when it is not
     * on file.
     *
     * $locations holds every location, active or not, keyed by lower-cased
     * code, because locations.code is unique: an inactive match has to be
     * recognised rather than re-created. It is passed in and updated in place
     * so a new code appearing on several rows creates one location, not one
     * per row.
     *
     * A location cannot exist without a hub (the receiving screens offer
     * locations by hub), so with no hub to put it in the row is refused rather
     * than creating a shelf nothing will ever offer.
     *
     * @param  Collection<string,Location>  $locations
     * @param  array<int,string>            $created    codes created so far; appended to
     * @return array{0:?int, 1:?string}     [location id, error message]
     */
    private function resolveOrCreateLocation(string $code, ?int $hubId, Collection $locations, array &$created): array
    {
        $key = mb_strtolower($code);
        $location = $locations->get($key);

        if ($location) {
            if (! $location->is_active) {
                return [null, "location '{$code}' exists but is inactive — reactivate it on the Locations page first"];
            }

            return [$location->id, null];
        }

        if (! $hubId) {
            return [null, "location '{$code}' is not on file, and there is no hub to create it in (give a valid hub, or add a hub named " . self::DEFAULT_HUB_NAME . ")"];
        }

        try {
            $location = Location::create([
                'code'        => $code,
                // description is NOT NULL on the Locations form; the code is
                // the best label available. Edit it on the Locations page.
                'description' => $code,
                'hub_id'      => $hubId,
                'is_active'   => true,
                'created_by'  => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            return [null, "could not create location '{$code}' — {$e->getMessage()}"];
        }

        ActivityLog::log('Created Location', "Code: {$location->code} | auto-created by a machines import");

        $locations->put($key, $location);
        $created[] = $location->code;

        return [$location->id, null];
    }

    /**
     * Two-file import: machines.csv defines the machines, machine_parts.csv
     * hangs parts off them by serial number.
     *
     * Either file may be uploaded on its own. Parts attach to a machine from
     * the machines file in the same upload, or to one already on file — so a
     * machine can be loaded now and its parts later.
     *
     * Wrapped in one transaction so a file that blows up halfway does not leave
     * machines behind without their parts. Skips are deliberate and do not roll
     * anything back; only an unexpected exception does.
     */
    public function batchStore(Request $request)
    {
        $this->authorizeBatchUpload();

        $request->validate([
            'machines_file' => 'nullable|file|mimes:csv,txt',
            'parts_file'    => 'nullable|file|mimes:csv,txt',
        ]);

        if (! $request->hasFile('machines_file') && ! $request->hasFile('parts_file')) {
            return back()->withErrors([
                'machines_file' => 'Upload at least one file — machines, machine parts, or both.',
            ]);
        }

        $machinesFile = $request->file('machines_file');
        $partsFile    = $request->file('parts_file');

        [$machines, $parts] = DB::transaction(function () use ($machinesFile, $partsFile) {
            // One location map shared by both files, so a location created for
            // a machine is found again by that machine's parts.
            $locations = Location::get()
                ->mapWithKeys(fn ($l) => [mb_strtolower(trim($l->code)) => $l]);

            $machines = $machinesFile
                ? $this->importMachineRows($machinesFile, $locations)
                : [
                    'imported' => 0, 'skipped' => 0, 'reasons' => [], 'serials' => [], 'machineHubs' => [],
                    'createdCustomers' => [], 'createdLocations' => [], 'createdCategories' => [], 'createdSuppliers' => [],
                ];

            $parts = $partsFile
                ? $this->importMachinePartRows($partsFile, $machines['serials'], $machines['machineHubs'], $locations)
                : ['imported' => 0, 'topped_up' => 0, 'skipped' => 0, 'created_parts' => 0, 'createdLocations' => [], 'reasons' => []];

            return [$machines, $parts];
        });

        $createdLocations = array_values(array_unique(array_merge(
            $machines['createdLocations'],
            $parts['createdLocations']
        )));

        ActivityLog::log(
            'Batch Uploaded Machines',
            "{$machines['imported']} machine(s), {$parts['imported']} part(s), "
            . "{$parts['topped_up']} added to existing quantity, "
            . count($machines['createdCustomers']) . ' new customer(s), '
            . count($machines['createdCategories']) . ' new category(ies), '
            . count($machines['createdSuppliers']) . ' new supplier(s), '
            . count($createdLocations) . ' new location(s), '
            . "{$parts['created_parts']} new catalogue item(s), "
            . ($machines['skipped'] + $parts['skipped']) . ' row(s) skipped'
        );

        return redirect()->route('machines.index')
            ->with('success', $this->batchSummary($machines, $parts, $createdLocations));
    }

    /**
     * @param  Collection<string,Location>  $locations  shared with the parts import
     * @return array{imported:int, skipped:int, reasons:array, serials:array<string,int>, machineHubs:array<string,int|null>, createdCustomers:array<int,string>, createdLocations:array<int,string>, createdCategories:array<int,string>, createdSuppliers:array<int,string>}
     *         serials maps lower-cased serial => new machines.id, and is what
     *         the parts file resolves machine_serial_no against.
     *         machineHubs maps the same serial => the hub the machine was filed
     *         in, which is where its parts' new locations are created.
     *         createdCustomers / createdLocations / createdCategories /
     *         createdSuppliers list what this import added.
     */
    private function importMachineRows(UploadedFile $file, Collection $locations): array
    {
        [$header, $records] = $this->readCsv($file);

        // Every category and supplier, active or not: a code or name that is on
        // file but deactivated must resolve to that row rather than create a
        // second one under the same label. Unknown ones are created below and
        // put back into these maps, so a new label on several rows is created
        // once.
        $byCode = fn ($rows, $field) => $rows->mapWithKeys(fn ($r) => [mb_strtolower(trim($r->$field)) => $r->id]);
        $categories = $byCode(Category::get(), 'code');
        $suppliers  = $byCode(Supplier::get(), 'name');
        $createdCategories = [];
        $createdSuppliers = [];

        // Matched by name because the CSV carries no ids. A name that is not on
        // file is reported, not created: hubs are a short fixed list.
        $hubs = Hub::get()->mapWithKeys(fn ($h) => [mb_strtolower(trim($h->hub_name)) => $h->id]);

        // A restricted user files everything against their own branch whatever
        // the sheet says, the same clamp store() applies.
        $forcedHub = $this->hubScope();

        // Used when the row's hub column is blank. Null if no hub is called
        // Manila, in which case a new location cannot be created for the row.
        $defaultHub = $hubs->get(mb_strtolower(self::DEFAULT_HUB_NAME));

        // Every customer, active or not, keyed by lower-cased name. customers.name
        // is unique, so an inactive match must be recognised here rather than
        // re-created. New customers are put back into this map so the same new
        // name on a later row reuses the record instead of creating a second.
        $customers = Customer::get()
            ->mapWithKeys(fn ($c) => [mb_strtolower(trim($c->name)) => $c]);
        $createdCustomers = [];
        $createdLocations = [];

        // serial_no carries a unique index — it is the machine's natural key,
        // which is both what makes a row a duplicate and what the parts file
        // points at.
        //
        // Read past the hub scope: the unique index spans every branch, so a
        // serial filed in another hub has to be skipped here too — otherwise
        // the insert throws and rolls back the whole upload.
        $onFile = Machine::withoutGlobalScope('hub')->pluck('serial_no')->map(fn ($s) => mb_strtolower(trim($s)))->flip();

        $serials = [];
        $machineHubs = [];
        $imported = 0;
        $skipped = 0;
        $reasons = [];

        foreach ($records as $i => $row) {
            $line = $i + 2; // +1 for the header, +1 because humans count from 1
            $data = $this->rowToPayload($header, $row, self::MACHINE_BATCH_COLUMNS);

            if ($data === null) {
                $skipped++;
                $reasons[] = "line {$line}: column count does not match the header";
                continue;
            }

            $serial = $data['serial_no'] ?? '';
            $key = mb_strtolower($serial);

            if ($serial === '' || ($data['description'] ?? '') === '') {
                $skipped++;
                $reasons[] = "line {$line}: serial_no and description are required";
                continue;
            }

            if ($onFile->has($key)) {
                $skipped++;
                $reasons[] = "{$serial}: already on file — an import never touches an existing machine";
                continue;
            }

            if (isset($serials[$key])) {
                $skipped++;
                $reasons[] = "{$serial}: listed twice in the file";
                continue;
            }

            // machines.category_id is NOT NULL, so this one is not optional.
            // An unknown code is created further down, with the other records
            // this row may add.
            if ($data['category'] === '') {
                $skipped++;
                $reasons[] = "{$serial}: category is required";
                continue;
            }

            // The hub the machine is filed in, and where a new location goes:
            // the user's own if restricted, else the row's hub column, else
            // the default (Manila). A name that is given but unknown is a typo
            // worth reporting, not something to quietly replace with Manila.
            $hubId = $forcedHub;
            if ($hubId === null) {
                if ($data['hub'] !== '') {
                    $hubId = $hubs->get(mb_strtolower($data['hub']));
                    if (! $hubId) {
                        $skipped++;
                        $reasons[] = "{$serial}: hub '{$data['hub']}' is not on file";
                        continue;
                    }
                } else {
                    $hubId = $defaultHub;
                }
            }

            try {
                $dateReceived = Carbon::parse($data['date_received'] ?: 'now')->toDateString();
            } catch (\Throwable $e) {
                $skipped++;
                $reasons[] = "{$serial}: '{$data['date_received']}' is not a readable date";
                continue;
            }

            // Everything below can create records, so every check that can skip
            // the row comes first — a skipped row never leaves an orphan
            // customer or location behind.
            $customer = null;
            if ($data['customer'] !== '') {
                $customerKey = mb_strtolower($data['customer']);
                $customer = $customers->get($customerKey);

                if ($customer && ! $customer->is_active) {
                    $skipped++;
                    $reasons[] = "{$serial}: customer '{$data['customer']}' exists but is inactive — reactivate it in Settings first";
                    continue;
                }
            }

            // Location last among the checks: it is the only remaining step
            // that can still skip the row, and the customer is not yet created.
            $locationId = null;
            if ($data['location'] !== '') {
                [$locationId, $error] = $this->resolveOrCreateLocation(
                    $data['location'], $hubId, $locations, $createdLocations
                );

                if ($error) {
                    $skipped++;
                    $reasons[] = "{$serial}: {$error}";
                    continue;
                }
            }

            // Category and supplier: created when not on file, the same
            // trade-off as customers — a typo becomes a new entry to tidy by
            // hand, and the result message lists them so it is easy to spot.
            $categoryKey = mb_strtolower($data['category']);
            $categoryId = $categories->get($categoryKey);

            if (! $categoryId) {
                $category = Category::create([
                    'code'        => $data['category'],
                    // description is required on the Categories form; the code
                    // is the best label available. Edit it in Settings.
                    'description' => $data['category'],
                    'is_active'   => true,
                ]);
                ActivityLog::log('Created Category', "Code: {$category->code} | auto-created by a machines import");

                $categoryId = $category->id;
                $categories->put($categoryKey, $categoryId);
                $createdCategories[] = $category->code;
            }

            $supplierId = null;
            if ($data['supplier'] !== '') {
                $supplierKey = mb_strtolower($data['supplier']);
                $supplierId = $suppliers->get($supplierKey);

                if (! $supplierId) {
                    $supplier = Supplier::create(['name' => $data['supplier'], 'is_active' => true]);
                    ActivityLog::log('Created Supplier', "Name: {$supplier->name} | auto-created by a machines import");

                    $supplierId = $supplier->id;
                    $suppliers->put($supplierKey, $supplierId);
                    $createdSuppliers[] = $supplier->name;
                }
            }

            $customerId = null;
            if ($data['customer'] !== '') {
                if (! $customer) {
                    $customer = Customer::create([
                        'name'      => $data['customer'],
                        'is_active' => true,
                    ]);
                    $customers->put(mb_strtolower($data['customer']), $customer);
                    $createdCustomers[] = $customer->name;
                }

                $customerId = $customer->id;
            }

            $machine = Machine::create([
                'supplier_id'   => $supplierId,
                'location_id'   => $locationId,
                'hub_id'        => $hubId,
                'customer_id'   => $customerId,
                'serial_no'     => $serial,
                'description'   => $data['description'],
                'model'         => $data['model'] ?: null,
                'category_id'   => $categoryId,
                'ati_po'        => $data['ati_po'] ?: null,
                'date_received' => $dateReceived,
                'unit_cost'     => is_numeric($data['unit_cost'] ?? '') ? $data['unit_cost'] : 0,
                'status'        => self::IMPORT_MACHINE_STATUS,
                'created_by'    => auth()->id(),
            ]);

            $serials[$key] = $machine->id;
            $machineHubs[$key] = $hubId;
            $imported++;
        }

        return compact(
            'imported', 'skipped', 'reasons', 'serials', 'machineHubs',
            'createdCustomers', 'createdLocations', 'createdCategories', 'createdSuppliers'
        );
    }

    /**
     * @param array<string,int>      $serials     lower-cased serial => machines.id, from
     *                                            the machines file just imported (empty
     *                                            for a parts-only upload). A serial not in
     *                                            here is looked up among existing machines.
     * @param array<string,int|null> $machineHubs the hub each of those machines was filed in.
     * @param Collection<string,Location> $locations shared with the machines import
     *
     * Unknown part numbers are added to the catalogue rather than skipped, so a
     * machine never arrives missing half its parts. part_description fills the
     * NOT NULL description; if it is blank the part number stands in for it.
     *
     * Unknown locations are created too, in the hub of the machine the part is
     * going on (or the restricted user's own hub).
     */
    private function importMachinePartRows(UploadedFile $file, array $serials, array $machineHubs, Collection $locations): array
    {
        [$header, $records] = $this->readCsv($file);

        $forcedHub = $this->hubScope();

        // Soft-deleted parts stay invisible: a part_no that was deleted on
        // purpose gets re-created rather than silently resurrected.
        $catalogue = ItemPart::pluck('id', 'part_no')
            ->mapWithKeys(fn ($id, $partNo) => [mb_strtolower(trim($partNo)) => $id]);

        // Machines already in the system, for a parts row whose machine is not
        // in this upload's machines file. The model's hub scope applies, so a
        // restricted user can only add parts to their own branch's machines.
        $onFile = Machine::get(['id', 'serial_no', 'hub_id'])
            ->mapWithKeys(fn ($m) => [mb_strtolower(trim($m->serial_no)) => $m]);

        // Serials already installed, per machine and part, so uploading the
        // same parts file twice does not install a serialised part twice.
        // Unserialised rows cannot be told apart from a genuine second batch,
        // so they are not checked.
        $installed = MachinePart::whereNotNull('serial_no')
            ->get(['machine_id', 'item_part_id', 'serial_no'])
            ->mapWithKeys(fn ($p) => ["{$p->machine_id}|{$p->item_part_id}|" . mb_strtolower(trim($p->serial_no)) => true]);

        // Unserialised parts still fitted, per machine and part. A second batch
        // of the same part is added to this row's quantity instead of becoming
        // a row of its own. Only fitted rows: a batch already pulled (Pending
        // Out / Out) is gone from the machine, so new units start a fresh row.
        // Oldest row wins when there are several, so the choice is stable.
        $loose = MachinePart::whereNull('serial_no')
            ->where('status', MachinePart::STATUS_ASSEMBLED)
            ->orderBy('id')
            ->get()
            ->reverse()
            ->mapWithKeys(fn ($p) => ["{$p->machine_id}|{$p->item_part_id}" => $p]);

        $imported = 0;
        $toppedUp = 0;
        $skipped = 0;
        $createdParts = 0;
        $createdLocations = [];
        $reasons = [];

        foreach ($records as $i => $row) {
            $line = $i + 2;
            $data = $this->rowToPayload($header, $row, self::MACHINE_PART_BATCH_COLUMNS);

            if ($data === null) {
                $skipped++;
                $reasons[] = "line {$line}: column count does not match the header";
                continue;
            }

            $machineSerial = $data['machine_serial_no'] ?? '';
            $partNo = $data['part_no'] ?? '';

            if ($machineSerial === '' || $partNo === '') {
                $skipped++;
                $reasons[] = "line {$line}: machine_serial_no and part_no are required";
                continue;
            }

            // This upload's machines first, then the ones already on file.
            $machineKey = mb_strtolower($machineSerial);
            $existing = $onFile->get($machineKey);
            $machineId = $serials[$machineKey] ?? $existing?->id;
            $machineHub = $machineHubs[$machineKey] ?? $existing?->hub_id;

            if (! $machineId) {
                $skipped++;
                $reasons[] = "{$machineSerial}: no machine with that serial — upload it in the machines file first";
                continue;
            }

            // Resolved before the part is created, so a row skipped over its
            // location does not leave a catalogue entry behind.
            $locationId = null;
            if ($data['location'] !== '') {
                [$locationId, $error] = $this->resolveOrCreateLocation(
                    $data['location'],
                    $forcedHub ?? $machineHub,
                    $locations,
                    $createdLocations
                );

                if ($error) {
                    $skipped++;
                    $reasons[] = "{$machineSerial}/{$partNo}: {$error}";
                    continue;
                }
            }

            // The id the spreadsheet could never supply. Resolve, or mint.
            $partKey = mb_strtolower($partNo);
            $itemPartId = $catalogue->get($partKey);

            // A part not yet in the catalogue cannot already be installed, so
            // this only needs checking for a known part number.
            $installedKey = "{$machineId}|{$itemPartId}|" . mb_strtolower($data['serial_no']);
            if ($itemPartId && $data['serial_no'] !== '' && $installed->has($installedKey)) {
                $skipped++;
                $reasons[] = "{$machineSerial}/{$partNo}: serial {$data['serial_no']} is already installed on this machine";
                continue;
            }

            if (! $itemPartId) {
                $itemPart = ItemPart::create([
                    'part_no'       => $partNo,
                    'description'   => $data['part_description'] ?: $partNo,
                    'type'          => $data['part_type'] ?: 'Part',
                    'unit_cost'     => 0,
                    'min_stock'     => 0,
                    // A row that bothered to record a serial is describing a
                    // serialised part; nothing else in the file says so.
                    'is_serialized' => $data['serial_no'] !== '',
                    'is_active'     => true,
                    'remarks'       => 'Created by machine batch upload',
                ]);

                $itemPartId = $itemPart->id;
                // Keep the map current so a part named twice is created once.
                $catalogue->put($partKey, $itemPartId);
                $createdParts++;
            }

            $quantity = (int) ($data['quantity'] ?? 0);
            $quantity = $quantity > 0 ? $quantity : 1;

            // The existing row keeps its own location, purpose and remarks;
            // only the count changes.
            $looseKey = "{$machineId}|{$itemPartId}";
            if ($data['serial_no'] === '' && $loose->has($looseKey)) {
                $loose->get($looseKey)->increment('quantity', $quantity);
                $toppedUp++;
                continue;
            }

            $machinePart = MachinePart::create([
                'machine_id'   => $machineId,
                'item_part_id' => $itemPartId,
                'quantity'     => $quantity,
                'serial_no'    => $data['serial_no'] ?: null,
                'location_id'  => $locationId,
                'purpose'      => $data['purpose'] ?: null,
                'remarks'      => $data['remarks'] ?: null,
                'status'       => self::IMPORT_PART_STATUS,
                'assembled_by' => auth()->id(),
                'assembled_at' => now(),
            ]);

            if ($data['serial_no'] !== '') {
                $installed->put("{$machineId}|{$itemPartId}|" . mb_strtolower($data['serial_no']), true);
            } else {
                // So the same part twice in one file also lands on one row.
                $loose->put($looseKey, $machinePart);
            }

            $imported++;
        }

        return [
            'imported'         => $imported,
            'topped_up'        => $toppedUp,
            'skipped'          => $skipped,
            'created_parts'    => $createdParts,
            'createdLocations' => $createdLocations,
            'reasons'          => $reasons,
        ];
    }

    /**
     * Skipped rows used to vanish without a word in the other batch screens, so
     * a wholly-failed import still reported "Imported 0". Reasons are deduped
     * and capped — a 400-row file with one bad column would otherwise render a
     * flash message longer than the page.
     *
     * @param array<int,string> $createdLocations codes added by either file
     */
    private function batchSummary(array $machines, array $parts, array $createdLocations): string
    {
        $message = "Imported {$machines['imported']} machine(s) and {$parts['imported']} part(s).";

        if ($parts['topped_up'] > 0) {
            $message .= " {$parts['topped_up']} part row(s) without a serial were added to the quantity already on the machine.";
        }

        if ($parts['created_parts'] > 0) {
            $message .= " {$parts['created_parts']} new item(s) added to the parts catalogue.";
        }

        // Listed by name so a typo ("Acme Corpp") is easy to spot right after
        // the upload and fix in Settings.
        foreach ([
            'createdCustomers'  => 'customer(s)',
            'createdCategories' => 'category(ies)',
            'createdSuppliers'  => 'supplier(s)',
        ] as $field => $noun) {
            $names = $machines[$field];

            if (! empty($names)) {
                $message .= ' ' . count($names) . " new {$noun} created: "
                    . implode(', ', array_slice($names, 0, 5))
                    . (count($names) > 5 ? ', …' : '') . '.';
            }
        }

        // Same for locations: "A-01 " vs "A01" now makes a new shelf instead of
        // failing the row, so the codes are listed to be checked.
        if (! empty($createdLocations)) {
            $message .= ' ' . count($createdLocations) . ' new location(s) created: '
                . implode(', ', array_slice($createdLocations, 0, 5))
                . (count($createdLocations) > 5 ? ', …' : '') . '.';
        }

        $skipped = $machines['skipped'] + $parts['skipped'];

        if ($skipped > 0) {
            $unique = array_values(array_unique(array_merge($machines['reasons'], $parts['reasons'])));
            $message .= " {$skipped} row(s) skipped: " . implode('; ', array_slice($unique, 0, 5))
                . (count($unique) > 5 ? '; …' : '.');
        }

        return $message;
    }
}