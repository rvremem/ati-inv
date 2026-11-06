<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MaintenanceContract;
use App\Models\Location;
use App\Http\Controllers\Concerns\ImportsCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
        $orderColumns = ['customer', 'equipment_type', 'serial_no', 'start_date', 'end_date'];
        $now = now();

        $base = MaintenanceContract::query();

        // Unfiltered total (before the search box is applied).
        $recordsTotal = MaintenanceContract::count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                foreach (['customer', 'equipment_type', 'serial_no', 'brand', 'po_no', 'sites',
                          'company', 'branch', 'account_manager', 'description',
                          'location', 'remarks'] as $col) {
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
            $base->orderBy('end_date', 'asc');
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
                'client' => $c->customer,
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
        $locations = Location::where('is_active', true)->get();
        return view('maintenance.create', compact('locations'));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $validated = $request->validate([
            'account_manager' => 'required',
            'equipment_type' => 'required',
            'customer' => 'required|string|max:255',
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
        $locations = Location::where('is_active', true)->get();
        return view('maintenance.edit', compact('contract', 'locations'));
    }

    public function update(Request $request, MaintenanceContract $contract)
    {
        $this->authorizeManage();
        $validated = $request->validate([
            'account_manager' => 'required',
            'equipment_type' => 'required',
            'customer' => 'required|string|max:255',
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

    /**
     * Narrows a contract query to one summary card's bucket. Uses the same
     * boundaries as reportSummary() so a card's count and its filtered table
     * always agree. Unknown states are ignored (no filter).
     */
    private function applyExpiryState($query, ?string $state)
    {
        $today = now()->startOfDay();
        $soon = now()->addDays(30)->endOfDay();

        return match ($state) {
            'expired' => $query->whereNotNull('end_date')->where('end_date', '<', $today),
            'soon'    => $query->whereNotNull('end_date')->whereBetween('end_date', [$today, $soon]),
            'ok'      => $query->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', $soon)),
            default   => $query,
        };
    }

    public function reportData(Request $request)
    {
        $this->authorizeManage();

        // Days Left and Status both derive from end_date, so they sort by it.
        $orderColumns = ['customer', 'equipment_type', 'serial_no', 'po_no', 'start_date', 'end_date', 'end_date', 'end_date'];

        $base = MaintenanceContract::query()->where('is_active', true);
        $this->applyExpiryState($base, $request->input('state'));
        $recordsTotal = (clone $base)->count();

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                foreach (['customer', 'equipment_type', 'serial_no', 'po_no', 'brand', 'company'] as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
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

        $data = $base->get()->map(function ($c) use ($today) {
            $daysLeft = $c->end_date ? (int) $today->diffInDays($c->end_date, false) : null;

            return [
                'client'     => $c->customer ?: '—',
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
    public function reportCsv(Request $request)
    {
        $this->authorizeManage();

        $state = in_array($request->input('state'), ['expired', 'soon', 'ok'], true) ? $request->input('state') : null;
        $today = now()->startOfDay();
        $filename = 'maintenance_expiry_report_' . ($state ? $state . '_' : '') . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($today, $state) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Customer', 'Equipment', 'Serial No', 'PO No', 'Start Date', 'End Date', 'Days Left', 'Status']);

            $query = MaintenanceContract::where('is_active', true);
            $this->applyExpiryState($query, $state);
            $rows = $query->orderBy('end_date', 'asc')->cursor();

            foreach ($rows as $c) {
                $daysLeft = $c->end_date ? (int) $today->diffInDays($c->end_date, false) : null;

                $status = $daysLeft === null ? 'Unknown'
                    : ($daysLeft < 0 ? 'Expired' : ($daysLeft <= 30 ? 'Expiring Soon' : 'Active'));

                fputcsv($out, [
                    $c->customer ?? '',
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
            'sampleRows' => self::SAMPLE_ROWS,
        ]);
    }

    /**
     * Downloads a ready-to-fill CSV template: the header row plus two example
     * rows. 'customer' is stored as written — it is not looked up anywhere.
     */
    public function sampleCsv()
    {
        $this->authorizeManage();

        return $this->streamCsv(self::BATCH_COLUMNS, self::SAMPLE_ROWS, 'maintenance_contracts_template.csv');
    }

    /**
     * Every column on maintenance_contracts except remarks and end_date is NOT
     * NULL, so a row missing any of these cannot be inserted at all. Checked up
     * front to report which field is missing, rather than letting the insert
     * fail and reporting nothing useful.
     */
    private const REQUIRED_BATCH_FIELDS = [
        'account_manager', 'equipment_type', 'customer', 'po_no', 'sites', 'brand',
        'description', 'location', 'company', 'branch', 'serial_no', 'start_date',
    ];

    /**
     * Reads a date cell as YYYY-MM-DD or MM/DD/YYYY, nothing else.
     *
     * Carbon::parse() was too forgiving for a spreadsheet: it read 03/04/2026
     * however it pleased and rolled 2026-02-30 over into March without a word,
     * so a typo became a wrong expiry rather than a skipped row. MM/DD/YYYY is
     * what Excel writes on a Philippine-locale PC.
     */
    private function parseCsvDate(string $value): ?Carbon
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m)) {
            [$month, $day, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? Carbon::create($year, $month, $day)->startOfDay() : null;
    }

    private const COVERAGE_WORDS = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6,
        'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12,
        'eighteen' => 18, 'twenty-four' => 24, 'twenty four' => 24, 'thirty' => 30,
        'thirty-six' => 36, 'thirty six' => 36, 'forty-eight' => 48, 'forty eight' => 48,
        'sixty' => 60,
    ];

    /**
     * Reads a coverage cell the way people actually type it into the
     * spreadsheet: "36", "36 months", "36mos", "1 year", "2 yrs", "1.5 years",
     * "1 year and 6 months", "three years". Bare numbers are months. Returns
     * null when the cell can't be read as a whole number of months, or is
     * ambiguous like "12-24 months", so the row is skipped rather than
     * imported with a guessed coverage.
     */
    private function parseCoverage(string $value): ?int
    {
        $text = mb_strtolower(trim($value));

        // "a year" / "an year" → "1 year"
        $text = preg_replace('/\ban?\s+(year|month)/', '1 $1', $text);

        // Longest words first so "twenty four" wins over a stray "four".
        $words = self::COVERAGE_WORDS;
        uksort($words, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($words as $word => $number) {
            $text = preg_replace('/\b' . preg_quote($word, '/') . '\b/', (string) $number, $text);
        }

        // Contract-style "One (1) Year" / "Thirty-Six (36) Months" says the
        // number twice; keep it once.
        $text = preg_replace('/\b(\d+(?:\.\d+)?)\s*\(\s*\1\s*\)/', '$1', $text);

        preg_match_all(
            '/(\d+(?:\.\d+)?)\s*-?\s*(years?|yrs?|y|months?|mons?|mths?|mos?|m)?(?![a-z])/',
            $text,
            $matches,
            PREG_SET_ORDER
        );

        if ($matches === []) {
            return null;
        }

        $withUnit = array_filter($matches, fn ($m) => ($m[2] ?? '') !== '');

        if ($withUnit === []) {
            // No unit anywhere: a single number is months, more than one is a guess.
            if (count($matches) !== 1) {
                return null;
            }
            $total = (float) $matches[0][1];
        } else {
            // A number without a unit next to ones with units ("12-24 months")
            // can't be placed, so the cell is not read at all.
            if (count($withUnit) !== count($matches)) {
                return null;
            }
            $total = 0.0;
            foreach ($withUnit as $m) {
                $total += str_starts_with($m[2], 'y') ? (float) $m[1] * 12 : (float) $m[1];
            }
        }

        $months = (int) round($total);

        // 1.3 years is 15.6 months — not a coverage anyone wrote on purpose.
        return $months > 0 && abs($total - $months) < 0.01 ? $months : null;
    }

    /** "N/A", "NA", "n.a.", "none", "-": a serial cell that says there is none. */
    private function isNoSerial(?string $serial): bool
    {
        $value = str_replace(['.', ' '], '', mb_strtolower(trim((string) $serial)));

        return in_array($value, ['n/a', 'na', 'none', '-', '--'], true);
    }

    /**
     * A serial already under contract is a renewal, not a new contract: the
     * file row overwrites what is on file instead of adding a second row.
     *
     * Every contract on file for the serial is updated, so rows duplicated by
     * earlier uploads come out agreeing with each other. A row with the same
     * dates as the one on file is a re-upload and changes nothing; a row that
     * starts before it is an older spreadsheet and would roll the renewal back.
     *
     * A serial of N/A identifies nothing, so those rows are never renewals
     * and never skipped as already on file: one PO often covers several
     * unserialised items, and each is its own contract. The catch is that
     * uploading the same file twice adds its N/A rows twice.
     */
    public function batchStore(Request $request)
    {
        $this->authorizeManage();
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt']);

        [$header, $records] = $this->readCsv($request->file('csv_file'));

        $onFile = MaintenanceContract::orderBy('id')->get();

        $bySerial = $onFile
            ->reject(fn ($c) => $this->isNoSerial($c->serial_no))
            ->groupBy(fn ($c) => mb_strtolower(trim($c->serial_no)));

        $imported = 0;
        $renewed = 0;
        $rowsUpdated = 0;
        $skippedRows = [];
        $importedLabels = [];
        $renewedLabels = [];

        // Records why the current row was not imported. $group is the reason
        // without its row-specific values, for the per-reason counts on the
        // results page; it defaults to the reason itself. $fixable is false
        // for a row whose contract is already on file: nothing is wrong with
        // it, so it stays out of the rows-to-fix download.
        $skip = function (string $reason, ?string $group = null, bool $fixable = true) use (&$skippedRows, &$line, &$label, &$row) {
            $skippedRows[] = [
                'line' => $line,
                'label' => $label,
                'reason' => $reason,
                'group' => $group ?? $reason,
                'fixable' => $fixable,
                'row' => $row,
            ];
        };

        foreach ($records as $i => $row) {
            $line = $i + 2; // +1 for the header, +1 because humans count from 1
            $label = '';
            $data = $this->rowToPayload($header, $row, self::BATCH_COLUMNS);

            if ($data === null) {
                $skip('column count does not match the header');
                continue;
            }

            $missing = array_values(array_filter(
                self::REQUIRED_BATCH_FIELDS,
                fn ($field) => $data[$field] === ''
            ));

            if ($missing !== []) {
                $skip('missing ' . implode(', ', $missing));
                continue;
            }

            // Labelled by PO number from here on — it is what identifies the row
            // to whoever has to fix the spreadsheet.
            $label = $data['po_no'];

            $start = $this->parseCsvDate($data['start_date']);
            if (! $start) {
                $skip(
                    "start_date '{$data['start_date']}' is not a valid YYYY-MM-DD or MM/DD/YYYY date",
                    'start_date is not a valid date'
                );
                continue;
            }

            $months = null;
            if ($data['coverage_months'] !== '') {
                $months = $this->parseCoverage($data['coverage_months']);
                if ($months === null) {
                    $skip(
                        "coverage_months '{$data['coverage_months']}' could not be read as months or years",
                        'coverage_months could not be read'
                    );
                    continue;
                }
            }

            if ($data['end_date'] !== '') {
                $end = $this->parseCsvDate($data['end_date']);
                if (! $end) {
                    $skip(
                        "end_date '{$data['end_date']}' is not a valid YYYY-MM-DD or MM/DD/YYYY date",
                        'end_date is not a valid date'
                    );
                    continue;
                }
            } else {
                // Blank end_date runs from the start date, never from today —
                // a backdated contract would otherwise expire in the wrong place.
                $end = $start->copy()->addMonths($months ?: 12);
            }

            // Same rule as the New Contract form (after:start_date). Without it
            // a swapped pair of dates imports as a contract that is already
            // expired on the day it is loaded.
            if ($end->lte($start)) {
                $skip('end_date must be after start_date');
                continue;
            }

            // Blank coverage is read off the two dates, so the months shown on
            // the contract agree with when it actually ends.
            $months ??= (int) round($start->diffInMonths($end));

            $payload = [
                'account_manager' => $data['account_manager'],
                'equipment_type' => $data['equipment_type'],
                'customer' => $data['customer'],
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
            ];

            if ($this->isNoSerial($data['serial_no'])) {
                try {
                    MaintenanceContract::create($payload);
                    $importedLabels[] = "{$data['po_no']}/{$data['serial_no']}";
                    $imported++;
                } catch (\Throwable $e) {
                    $skip($this->rowError($e));
                }
                continue;
            }

            $serialKey = mb_strtolower($data['serial_no']);
            $existing = $bySerial->get($serialKey);

            try {
                if ($existing) {
                    $sameDates = $existing->first(fn ($c) => $c->start_date->eq($start) && $c->end_date?->eq($end));
                    if ($sameDates) {
                        $skip(
                            "serial {$data['serial_no']} is already on file with these dates",
                            'serial is already on file with these dates',
                            false
                        );
                        continue;
                    }

                    $latestStart = $existing->max('start_date');
                    if ($start->lt($latestStart)) {
                        $skip(
                            "serial {$data['serial_no']} already has a newer contract starting {$latestStart->format('Y-m-d')}",
                            'serial already has a newer contract',
                            false
                        );
                        continue;
                    }

                    // A blank remarks cell keeps the note already on the contract.
                    if ($payload['remarks'] === null) {
                        unset($payload['remarks']);
                    }

                    // Renewed cover is live cover, even if the old row had been
                    // switched off when it lapsed.
                    $payload['is_active'] = true;

                    foreach ($existing as $contract) {
                        $contract->update($payload);
                        $rowsUpdated++;
                    }

                    $renewedLabels[] = "{$data['po_no']}/{$data['serial_no']}";
                    $renewed++;
                    continue;
                }

                $contract = MaintenanceContract::create($payload);
                // So a later row for the same serial in this file renews it.
                $bySerial->put($serialKey, collect([$contract]));
                $importedLabels[] = "{$data['po_no']}/{$data['serial_no']}";
                $imported++;
            } catch (\Throwable $e) {
                $skip($this->rowError($e));
            }
        }

        $skipped = count($skippedRows);

        $labels = array_merge(
            $importedLabels !== [] ? ['new: ' . implode(', ', $importedLabels)] : [],
            $renewedLabels !== [] ? ['renewed: ' . implode(', ', $renewedLabels)] : [],
        );

        // remarks is a 255-character column, so the PO/serial list is cut to
        // fit rather than letting a large file fail the log write.
        ActivityLog::log(
            'Batch Uploaded Maintenance Contracts',
            Str::limit(
                "{$imported} imported, {$renewed} renewed, {$skipped} skipped"
                . ($labels !== [] ? ' | ' . implode(' | ', $labels) : ''),
                252
            )
        );

        $extra = $renewed > 0
            ? "{$renewed} renewed (" . $rowsUpdated . ' existing contract row(s) updated).'
            : '';

        return $this->batchResultRedirect(
            redirect()->route('maintenance.index'),
            'Maintenance Contracts',
            'maintenance contract',
            $header,
            $skippedRows,
            ['Imported' => $imported, 'Renewed' => $renewed],
            $extra
        );
    }
}
