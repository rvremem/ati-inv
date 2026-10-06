<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MaintenanceContract;
use App\Models\Customer;
use App\Models\Location;
use App\Http\Controllers\Concerns\ImportsCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MaintenanceController extends Controller
{
    use ImportsCsv;

    /**
     * The batch-upload CSV column order. Single source of truth for the
     * downloadable template and the format shown on the upload page, so the two
     * can't drift apart. batchStore() still reads the uploaded file's own header,
     * so a CSV with a different column order (or without end_date) still imports.
     */
    public const BATCH_COLUMNS = [
        'account_manager', 'equipment_type', 'customer', 'po_no', 'sites',
        'brand', 'description', 'location', 'company', 'branch', 'serial_no',
        'coverage_months', 'start_date', 'end_date', 'remarks',
    ];

    private const SAMPLE_ROWS = [
        ['John Doe', 'Server Maintenance', 'ABC Corp', 'PO-001', 'Manila', 'Dell', 'Server Support', 'Manila HQ', 'ATI Corp', 'Metro Manila', 'SN-12345', '12', '2026-01-01', '2027-01-01', 'Monthly check'],
        ['Jane Smith', 'Network Support', 'XYZ Inc', 'PO-002', 'Cebu', 'Cisco', 'Router Support', 'Cebu Branch', 'ATI Corp', 'Cebu', 'SN-12346', '12', '2026-01-01', '2027-01-01', 'Quarterly audit'],
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * SA, Developer, Admin and Service Desk may add/edit/delete contracts, run
     * the batch upload and pull the expiry report. Every other role gets the
     * list read-only. isSA() already covers Developer.
     */
    private function canManage(): bool
    {
        $user = auth()->user();

        return $user->isSA() || $user->isAdmin() || $user->role === 'ServiceDesk';
    }

    private function authorizeManage(): void
    {
        if (!$this->canManage()) {
            abort(403);
        }
    }

    public function index()
    {
        // Rows are loaded via server-side DataTables (see contractsData) so the
        // page shell renders instantly instead of loading every contract at once.
        // The list itself is readable by every role; only the actions are gated.
        return view('maintenance.index', ['canManage' => $this->canManage()]);
    }

    public function contractsData(Request $request)
    {
        // Server-side DataTables endpoint: search + order + paginate in SQL,
        // returning only the current page of rows.
        $orderColumns = [
            'customers.name',
            'maintenance_contracts.equipment_type',
            'maintenance_contracts.serial_no',
            'maintenance_contracts.start_date',
            'maintenance_contracts.end_date',
        ];
        $now = now();

        $base = MaintenanceContract::query()
            ->leftJoin('customers', 'customers.id', '=', 'maintenance_contracts.customer_id')
            ->select('maintenance_contracts.*', 'customers.name as client_name');

        // Unfiltered total (before the search box is applied).
        $recordsTotal = MaintenanceContract::count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('customers.name', 'like', "%{$search}%");
                foreach (['equipment_type', 'serial_no', 'brand', 'po_no', 'sites',
                          'company', 'branch', 'account_manager', 'description',
                          'location', 'remarks'] as $col) {
                    $q->orWhere("maintenance_contracts.{$col}", 'like', "%{$search}%");
                }
            });
        }

        $recordsFiltered = (clone $base)->count();

        $orderIdx = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        if ($orderIdx !== null && isset($orderColumns[$orderIdx])) {
            $base->orderBy($orderColumns[$orderIdx], $orderDir);
        } else {
            $base->orderBy('maintenance_contracts.end_date', 'asc');
        }

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            // sqlsrv needs the ORDER BY above before OFFSET/FETCH is applied.
            $base->skip($start)->take($length);
        }

        // Don't hand edit links to roles that would be refused at the route.
        $canManage = $this->canManage();

        $data = $base->get()->map(function ($c) use ($now, $canManage) {
            $active = $c->is_active && $c->end_date && $c->end_date > $now;
            $expired = $c->end_date && $c->end_date <= $now;
            return [
                'client' => $c->client_name,
                'equipment_type' => $c->equipment_type,
                'serial_no' => $c->serial_no,
                'start_date' => $c->start_date ? $c->start_date->format('M d, Y') : '-',
                'end_date' => $c->end_date ? $c->end_date->format('M d, Y') : '-',
                'status' => $expired ? 'expired' : ($active ? 'active' : 'inactive'),
                'edit_url' => $canManage ? route('maintenance.edit', $c->id) : null,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create()
    {
        $this->authorizeManage();
        $customers = Customer::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        return view('maintenance.create', compact('customers', 'locations'));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $validated = $request->validate([
            'account_manager' => 'required',
            'equipment_type' => 'required',
            'customer_id' => 'required|exists:customers,id',
            'po_no' => 'required',
            'sites' => 'required',
            'brand' => 'required',
            'description' => 'required',
            'location' => 'required',
            'company' => 'required',
            'branch' => 'required',
            'serial_no' => 'required',
            'coverage_months' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'remarks' => 'nullable',
        ]);

        MaintenanceContract::create($validated);
        return redirect()->route('maintenance.index')->with('success', 'Maintenance contract created successfully.');
    }

    public function edit(MaintenanceContract $contract)
    {
        $this->authorizeManage();
        $customers = Customer::where('is_active', true)->get();
        $locations = Location::where('is_active', true)->get();
        return view('maintenance.edit', compact('contract', 'customers', 'locations'));
    }

    public function update(Request $request, MaintenanceContract $contract)
    {
        $this->authorizeManage();
        $validated = $request->validate([
            'account_manager' => 'required',
            'equipment_type' => 'required',
            'customer_id' => 'required|exists:customers,id',
            'po_no' => 'required',
            'sites' => 'required',
            'brand' => 'required',
            'description' => 'required',
            'location' => 'required',
            'company' => 'required',
            'branch' => 'required',
            'serial_no' => 'required',
            'coverage_months' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'remarks' => 'nullable',
            'is_active' => 'boolean',
        ]);

        $contract->update($validated);
        return redirect()->route('maintenance.index')->with('success', 'Maintenance contract updated successfully.');
    }

    public function destroy(MaintenanceContract $contract)
    {
        $this->authorizeManage();
        $contract->delete();
        return redirect()->route('maintenance.index')->with('success', 'Maintenance contract deleted successfully.');
    }

    /**
     * The report screen: summary totals plus a paged table.
     *
     * Totals are counted in SQL rather than by loading every contract. Rows
     * arrive 10 at a time from reportData(); printing goes through
     * reportPrint(), which renders the full list on its own page — paging the
     * screen and printing everything are different jobs.
     */
    public function report()
    {
        $this->authorizeManage();

        return view('maintenance.report', $this->reportSummary());
    }

    /** Expired / expiring-soon / not-yet-due counts, as three cheap aggregates. */
    private function reportSummary(): array
    {
        $today = now()->startOfDay();
        $soon = now()->addDays(30)->endOfDay();

        $active = fn () => MaintenanceContract::where('is_active', true);

        return [
            'expiredCount' => (clone $active())->whereNotNull('end_date')->where('end_date', '<', $today)->count(),
            'soonCount'    => (clone $active())->whereNotNull('end_date')->whereBetween('end_date', [$today, $soon])->count(),
            'okCount'      => (clone $active())->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', $soon))->count(),
            'totalCount'   => $active()->count(),
        ];
    }

    public function reportData(Request $request)
    {
        $this->authorizeManage();

        // Days Left and Status both derive from end_date, so they sort by it.
        $orderColumns = ['customer_id', 'equipment_type', 'serial_no', 'po_no', 'start_date', 'end_date', 'end_date', 'end_date'];

        $base = MaintenanceContract::query()->where('is_active', true);
        $recordsTotal = (clone $base)->count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                foreach (['equipment_type', 'serial_no', 'po_no', 'brand', 'company'] as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
                $q->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $recordsFiltered = (clone $base)->count();

        $orderIdx = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        $base->orderBy($orderColumns[$orderIdx] ?? 'end_date', $orderDir);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $base->skip($start)->take($length);
        }

        $today = now()->startOfDay();

        $data = $base->with('customer')->get()->map(function ($c) use ($today) {
            $daysLeft = $c->end_date ? (int) $today->diffInDays($c->end_date, false) : null;

            return [
                'client'     => $c->customer->name ?? '—',
                'equipment'  => $c->equipment_type ?? '—',
                'serial_no'  => $c->serial_no ?? '—',
                'po_no'      => $c->po_no ?? '—',
                'start_date' => $c->start_date ? $c->start_date->format('M d, Y') : '-',
                'end_date'   => $c->end_date ? $c->end_date->format('M d, Y') : '-',
                'days_left'  => $daysLeft,
                // Sent as a flag so the row highlight and the badge cannot
                // disagree with the Days Left figure beside them.
                'state'      => $daysLeft === null ? 'unknown'
                                : ($daysLeft < 0 ? 'expired' : ($daysLeft <= 30 ? 'soon' : 'ok')),
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
     * Exports every active contract as CSV.
     *
     * The whole list, not the page on screen — the table is paged to 10 rows
     * to keep it fast, so an export limited to the visible page would be the
     * same defect the old Print button had.
     */
    public function reportCsv()
    {
        $this->authorizeManage();

        $today = now()->startOfDay();
        $filename = 'maintenance_expiry_report_' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($today) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Customer', 'Equipment', 'Serial No', 'PO No', 'Start Date', 'End Date', 'Days Left', 'Status']);

            $rows = MaintenanceContract::with('customer')
                ->where('is_active', true)
                ->orderBy('end_date', 'asc')
                ->cursor();

            foreach ($rows as $c) {
                $daysLeft = $c->end_date ? (int) $today->diffInDays($c->end_date, false) : null;

                $status = $daysLeft === null ? 'Unknown'
                    : ($daysLeft < 0 ? 'Expired' : ($daysLeft <= 30 ? 'Expiring Soon' : 'Active'));

                fputcsv($out, [
                    $c->customer->name ?? '',
                    $c->equipment_type ?? '',
                    $c->serial_no ?? '',
                    $c->po_no ?? '',
                    // ISO dates so a spreadsheet sorts them correctly, rather
                    // than the "M d, Y" used on screen.
                    $c->start_date ? $c->start_date->format('Y-m-d') : '',
                    $c->end_date ? $c->end_date->format('Y-m-d') : '',
                    $daysLeft ?? '',
                    $status,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function batchUpload()
    {
        $this->authorizeManage();
        return view('maintenance.batch-upload', [
            'columns' => self::BATCH_COLUMNS,
            'sampleRows' => $this->sampleRows(),
        ]);
    }

    /**
     * Sample rows with the 'customer' column swapped for names that actually
     * exist. maintenance_contracts.customer_id is NOT NULL and is resolved from
     * this name, so a template quoting a customer that isn't on record produces
     * rows that silently fail to import.
     */
    private function sampleRows(): array
    {
        $names = Customer::where('is_active', true)
            ->orderBy('name')
            ->take(count(self::SAMPLE_ROWS))
            ->pluck('name')
            ->all();

        $rows = self::SAMPLE_ROWS;
        foreach ($rows as $i => $row) {
            if (isset($names[$i])) {
                // Index 2 is the 'customer' column in BATCH_COLUMNS.
                $rows[$i][2] = $names[$i];
            }
        }

        return $rows;
    }

    /**
     * Downloads a ready-to-fill CSV template: the header row plus two example
     * rows. 'customer' is matched against the customer name on import.
     */
    public function sampleCsv()
    {
        $this->authorizeManage();

        return $this->streamCsv(self::BATCH_COLUMNS, $this->sampleRows(), 'maintenance_contracts_template.csv');
    }

    /**
     * Every column on maintenance_contracts except remarks and end_date is NOT
     * NULL, so a row missing any of these cannot be inserted at all. Checked up
     * front to report which field is missing, rather than letting the insert
     * fail and reporting nothing useful.
     */
    private const REQUIRED_BATCH_FIELDS = [
        'account_manager', 'equipment_type', 'po_no', 'sites', 'brand',
        'description', 'location', 'company', 'branch', 'serial_no', 'start_date',
    ];

    public function batchStore(Request $request)
    {
        $this->authorizeManage();
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt']);

        [$header, $records] = $this->readCsv($request->file('csv_file'));

        // customer_id is NOT NULL and is resolved from the name in the file, so
        // an unmatched name is the single most common reason a row fails.
        $customers = Customer::where('is_active', true)->get()
            ->mapWithKeys(fn ($c) => [mb_strtolower(trim($c->name)) => $c->id]);

        $imported = 0;
        $skipped = 0;
        $reasons = [];

        foreach ($records as $i => $row) {
            $line = $i + 2; // +1 for the header, +1 because humans count from 1
            $data = $this->rowToPayload($header, $row, self::BATCH_COLUMNS);

            if ($data === null) {
                $skipped++;
                $reasons[] = "line {$line}: column count does not match the header";
                continue;
            }

            $missing = array_values(array_filter(
                self::REQUIRED_BATCH_FIELDS,
                fn ($field) => $data[$field] === ''
            ));

            if ($missing !== []) {
                $skipped++;
                $reasons[] = "line {$line}: missing " . implode(', ', $missing);
                continue;
            }

            // Labelled by PO number from here on — it is what identifies the row
            // to whoever has to fix the spreadsheet.
            $label = $data['po_no'];

            $customerId = $customers->get(mb_strtolower($data['customer']));
            if (! $customerId) {
                $skipped++;
                $reasons[] = "{$label}: customer '" . ($data['customer'] ?: '(blank)') . "' is not an active customer";
                continue;
            }

            try {
                $start = Carbon::parse($data['start_date']);
            } catch (\Throwable $e) {
                $skipped++;
                $reasons[] = "{$label}: start_date '{$data['start_date']}' is not a date";
                continue;
            }

            $months = ctype_digit($data['coverage_months']) ? (int) $data['coverage_months'] : 0;

            // Blank end_date is derived from the start date, as the upload page
            // promises — it used to be counted from today instead, which put the
            // expiry in the wrong place for any backdated contract.
            try {
                $end = $data['end_date'] !== ''
                    ? Carbon::parse($data['end_date'])
                    : $start->copy()->addMonths($months ?: 12);
            } catch (\Throwable $e) {
                $skipped++;
                $reasons[] = "{$label}: end_date '{$data['end_date']}' is not a date";
                continue;
            }

            try {
                MaintenanceContract::create([
                    'account_manager' => $data['account_manager'],
                    'equipment_type' => $data['equipment_type'],
                    'customer_id' => $customerId,
                    'po_no' => $data['po_no'],
                    'sites' => $data['sites'],
                    'brand' => $data['brand'],
                    'description' => $data['description'],
                    'location' => $data['location'],
                    'company' => $data['company'],
                    'branch' => $data['branch'],
                    'serial_no' => $data['serial_no'],
                    'coverage_months' => $months,
                    'start_date' => $start,
                    'end_date' => $end,
                    'remarks' => $data['remarks'] ?: null,
                ]);
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $reasons[] = "{$label}: {$e->getMessage()}";
            }
        }

        ActivityLog::log(
            'Batch Uploaded Maintenance Contracts',
            "{$imported} contract(s) imported, {$skipped} skipped"
        );

        return redirect()->route('maintenance.index')
            ->with('success', $this->batchSummary('maintenance contract', $imported, $skipped, $reasons));
    }
}
