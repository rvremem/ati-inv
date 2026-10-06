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
     */
    public const MACHINE_BATCH_COLUMNS = [
        'serial_no', 'description', 'model', 'category', 'customer',
        'supplier', 'location', 'ati_po', 'date_received', 'unit_cost',
    ];

    public const MACHINE_PART_BATCH_COLUMNS = [
        'machine_serial_no', 'part_no', 'part_description', 'part_type',
        'quantity', 'serial_no', 'location', 'purpose', 'remarks',
    ];

    /** Status a freshly received machine gets, matching store(). */
    private const IMPORT_MACHINE_STATUS = 'Receiving';

    /** Status an installed part gets, matching storePartForm(). */
    private const IMPORT_PART_STATUS = 'available';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        // machines.status carries the request lifecycle (Pending/Out/Return), so
        // the old correlated-subquery join onto machine_requests is redundant.
        $machines = Machine::with('category', 'customer', 'location')
            ->orderBy('serial_no')
            ->get();

        return view('machines.index', compact('machines'));
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

    public function addPart(Machine $machine)
    {
        $items = ItemPart::all();
        $locations = Location::where('is_active', true)->get();
        return view('machines.add-part', compact('machine', 'items', 'locations'));
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
        return redirect()->route('receiving.machines')->with('success', 'Parts added to machine successfully.');
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
        return view('machines.batch-upload', [
            'machineColumns' => self::MACHINE_BATCH_COLUMNS,
            'partColumns'    => self::MACHINE_PART_BATCH_COLUMNS,
            'machineRows'    => $this->machineSampleRows(),
            'partRows'       => $this->machinePartSampleRows(),
        ]);
    }

    public function machineTemplateCsv()
    {
        return $this->streamCsv(self::MACHINE_BATCH_COLUMNS, $this->machineSampleRows(), 'machines_template.csv');
    }

    public function machinePartTemplateCsv()
    {
        return $this->streamCsv(self::MACHINE_PART_BATCH_COLUMNS, $this->machinePartSampleRows(), 'machine_parts_template.csv');
    }

    /**
     * Sample rows quote lookup values that are really on file, so the spellings
     * are known-good. category is the one that matters most: machines.category_id
     * is NOT NULL, so a template naming a category nobody has produces a file
     * where every row skips.
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
        $location = Location::where('is_active', true)->orderBy('code')->value('code') ?? '';
        $today    = now()->toDateString();

        return [
            ['REPLACE-WITH-SERIAL-1', 'Sample machine', 'MODEL-A', $category, $customer, $supplier, $location, 'PO-0001', $today, '15000.00'],
            ['REPLACE-WITH-SERIAL-2', 'Another machine', 'MODEL-B', $category, $customer, '', $location, '', $today, '0'],
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
     * throwing undefined-key warnings on every optional field.
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
     * Two-file import: machines.csv defines the machines, machine_parts.csv
     * hangs parts off them by serial number.
     *
     * Wrapped in one transaction so a file that blows up halfway does not leave
     * machines behind without their parts. Skips are deliberate and do not roll
     * anything back; only an unexpected exception does.
     */
    public function batchStore(Request $request)
    {
        $request->validate([
            'machines_file' => 'required|file|mimes:csv,txt',
            'parts_file'    => 'nullable|file|mimes:csv,txt',
        ]);

        $machinesFile = $request->file('machines_file');
        $partsFile    = $request->file('parts_file');

        [$machines, $parts] = DB::transaction(function () use ($machinesFile, $partsFile) {
            $machines = $this->importMachineRows($machinesFile);

            $parts = $partsFile
                ? $this->importMachinePartRows($partsFile, $machines['serials'])
                : ['imported' => 0, 'skipped' => 0, 'created_parts' => 0, 'reasons' => []];

            return [$machines, $parts];
        });

        ActivityLog::log(
            'Batch Uploaded Machines',
            "{$machines['imported']} machine(s), {$parts['imported']} part(s), "
            . "{$parts['created_parts']} new catalogue item(s), "
            . ($machines['skipped'] + $parts['skipped']) . ' row(s) skipped'
        );

        return redirect()->route('machines.index')
            ->with('success', $this->batchSummary($machines, $parts));
    }

    /**
     * @return array{imported:int, skipped:int, reasons:array, serials:array<string,int>}
     *         serials maps lower-cased serial => new machines.id, and is what
     *         the parts file resolves machine_serial_no against.
     */
    private function importMachineRows(UploadedFile $file): array
    {
        [$header, $records] = $this->readCsv($file);

        $byCode = fn ($rows, $field) => $rows->mapWithKeys(fn ($r) => [mb_strtolower(trim($r->$field)) => $r->id]);
        $categories = $byCode(Category::where('is_active', true)->get(), 'code');
        $customers  = $byCode(Customer::where('is_active', true)->get(), 'name');
        $suppliers  = $byCode(Supplier::where('is_active', true)->get(), 'name');
        $locations  = $byCode(Location::where('is_active', true)->get(), 'code');

        // serial_no carries a unique index — it is the machine's natural key,
        // which is both what makes a row a duplicate and what the parts file
        // points at.
        $onFile = Machine::pluck('serial_no')->map(fn ($s) => mb_strtolower(trim($s)))->flip();

        $serials = [];
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
            $categoryId = $categories->get(mb_strtolower($data['category'] ?? ''));
            if (! $categoryId) {
                $skipped++;
                $reasons[] = "{$serial}: category '" . ($data['category'] ?: '(blank)') . "' is not an active category";
                continue;
            }

            // Blank means "not set"; a value that resolves to nothing is a typo
            // worth reporting rather than quietly storing as null.
            $optional = [
                'customer_id' => [$customers, $data['customer'] ?? '', 'customer'],
                'supplier_id' => [$suppliers, $data['supplier'] ?? '', 'supplier'],
                'location_id' => [$locations, $data['location'] ?? '', 'location'],
            ];

            $ids = [];
            $bad = null;
            foreach ($optional as $column => [$lookup, $label, $noun]) {
                if ($label === '') {
                    $ids[$column] = null;
                    continue;
                }
                $ids[$column] = $lookup->get(mb_strtolower($label));
                if (! $ids[$column]) {
                    $bad = "{$serial}: {$noun} '{$label}' is not on file";
                    break;
                }
            }

            if ($bad) {
                $skipped++;
                $reasons[] = $bad;
                continue;
            }

            try {
                $dateReceived = Carbon::parse($data['date_received'] ?: 'now')->toDateString();
            } catch (\Throwable $e) {
                $skipped++;
                $reasons[] = "{$serial}: '{$data['date_received']}' is not a readable date";
                continue;
            }

            $machine = Machine::create($ids + [
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
            $imported++;
        }

        return compact('imported', 'skipped', 'reasons', 'serials');
    }

    /**
     * @param array<string,int> $serials lower-cased serial => machines.id, from
     *                                   the machines file just imported.
     *
     * Unknown part numbers are added to the catalogue rather than skipped, so a
     * machine never arrives missing half its parts. part_description fills the
     * NOT NULL description; if it is blank the part number stands in for it.
     */
    private function importMachinePartRows(UploadedFile $file, array $serials): array
    {
        [$header, $records] = $this->readCsv($file);

        $locations = Location::where('is_active', true)->get()
            ->mapWithKeys(fn ($l) => [mb_strtolower(trim($l->code)) => $l->id]);

        // Soft-deleted parts stay invisible: a part_no that was deleted on
        // purpose gets re-created rather than silently resurrected.
        $catalogue = ItemPart::pluck('id', 'part_no')
            ->mapWithKeys(fn ($id, $partNo) => [mb_strtolower(trim($partNo)) => $id]);

        $onFile = Machine::pluck('id', 'serial_no')
            ->mapWithKeys(fn ($id, $s) => [mb_strtolower(trim($s)) => $id]);

        $imported = 0;
        $skipped = 0;
        $createdParts = 0;
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

            $machineKey = mb_strtolower($machineSerial);
            $machineId = $serials[$machineKey] ?? null;

            if (! $machineId) {
                $skipped++;
                $reasons[] = $onFile->has($machineKey)
                    ? "{$machineSerial}: machine was already on file, so its parts were skipped too"
                    : "{$machineSerial}: no machine with that serial in the machines file";
                continue;
            }

            $locationId = null;
            if (($data['location'] ?? '') !== '') {
                $locationId = $locations->get(mb_strtolower($data['location']));
                if (! $locationId) {
                    $skipped++;
                    $reasons[] = "{$machineSerial}/{$partNo}: location '{$data['location']}' is not on file";
                    continue;
                }
            }

            // The id the spreadsheet could never supply. Resolve, or mint.
            $partKey = mb_strtolower($partNo);
            $itemPartId = $catalogue->get($partKey);

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

            MachinePart::create([
                'machine_id'   => $machineId,
                'item_part_id' => $itemPartId,
                'quantity'     => $quantity > 0 ? $quantity : 1,
                'serial_no'    => $data['serial_no'] ?: null,
                'location_id'  => $locationId,
                'purpose'      => $data['purpose'] ?: null,
                'remarks'      => $data['remarks'] ?: null,
                'status'       => self::IMPORT_PART_STATUS,
                'assembled_by' => auth()->id(),
                'assembled_at' => now(),
            ]);

            $imported++;
        }

        return [
            'imported'      => $imported,
            'skipped'       => $skipped,
            'created_parts' => $createdParts,
            'reasons'       => $reasons,
        ];
    }

    /**
     * Skipped rows used to vanish without a word in the other batch screens, so
     * a wholly-failed import still reported "Imported 0". Reasons are deduped
     * and capped — a 400-row file with one bad column would otherwise render a
     * flash message longer than the page.
     */
    private function batchSummary(array $machines, array $parts): string
    {
        $message = "Imported {$machines['imported']} machine(s) and {$parts['imported']} part(s).";

        if ($parts['created_parts'] > 0) {
            $message .= " {$parts['created_parts']} new item(s) added to the parts catalogue.";
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
