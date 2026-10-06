<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\ItemPart;
use App\Models\Machine;
use App\Models\Receiving;
use App\Models\PartRequest;
use App\Models\MachineRequest;
use App\Models\MaintenanceContract;
use App\Models\DefectivePart;

class DashboardController extends Controller
{
    use \App\Http\Controllers\Concerns\ScopesByHub;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Role-split dashboard, ported from atiis.
     *
     * Every branch fills the same $data shape so the view can render whichever
     * panels are populated without null-checking each one. Roles map across as:
     * atiis "Maintenance" is this app's "ServiceDesk".
     */
    public function index()
    {
        $user = auth()->user();

        // SA / Developer / Admin get the full operations dashboard. isSA()
        // already covers Developer.
        $isAdminView = $user->isSA() || $user->isAdmin();

        $data = [
            'stats' => null,
            'recent_receiving' => collect(),
            'part_requests_pending' => collect(),
            'my_requests' => null,
            'low_stock' => collect(),
            'expiring_contracts' => collect(),
            'monthly_flow' => collect(),
            'machine_status' => ['data' => collect(), 'total' => 0],
        ];

        if ($isAdminView) {
            $snapshot = $this->stockSnapshot();
            $lowStock = $snapshot->filter(fn ($it) => (int) $it->min_stock > 0 && $it->on_hand < (int) $it->min_stock);

            $data['stats'] = [
                'total_items'        => $this->partsInHubScope()->where('is_active', true)->count(),
                'low_stock'          => $lowStock->count(),
                'total_machines'     => Machine::where('status', '<>', 'Inactive')->count(),
                'pending_requests'   => PartRequest::where('status', 'Pending')->count()
                                        + MachineRequest::where('status', 'Pending')->count(),
                'expiring_contracts' => $this->expiringContracts()->count(),
                'open_defectives'    => DefectivePart::count(),
            ];

            $data['low_stock'] = $lowStock
                ->sortBy(fn ($it) => (int) $it->min_stock > 0 ? $it->on_hand / (int) $it->min_stock : 0)
                ->take(6)
                ->values();
            $data['expiring_contracts'] = $this->expiringContracts()->take(6);
            $data['monthly_flow'] = $this->monthlyFlow();
            $data['machine_status'] = $this->machineStatus();
            $data['part_requests_pending'] = $this->getPendingPartRequests();
            $data['recent_receiving'] = $this->getRecentReceiving();
        } elseif ($user->role === 'Engineer') {
            $data['stats'] = [
                'total_items'    => $this->partsInHubScope()->where('is_active', true)->count(),
                'total_machines' => Machine::where('status', '<>', 'Inactive')->count(),
                'pending_own'    => PartRequest::where('requested_by', $user->id)->where('status', 'Pending')->count()
                                    + MachineRequest::where('requested_by', $user->id)->where('status', 'Pending')->count(),
                'maintenance'    => MaintenanceContract::where('is_active', true)->count(),
            ];
            // Scoped to the signed-in engineer. requested_by is a user id here,
            // where atiis stores a name.
            $data['my_requests'] = PartRequest::with(['itemPart', 'customer', 'machine'])
                ->where('requested_by', $user->id)
                ->latest()
                ->limit(20)
                ->get();
        } elseif ($user->role === 'ServiceDesk') {
            $expiring = $this->expiringContracts();
            $data['stats'] = [
                'expiring_soon' => $expiring->count(),
                'contracts'     => MaintenanceContract::where('is_active', true)->count(),
            ];
            $data['expiring_contracts'] = $expiring->take(12);
        } elseif ($user->role === 'PartsCenter') {
            $snapshot = $this->stockSnapshot();
            $lowStock = $snapshot->filter(fn ($it) => (int) $it->min_stock > 0 && $it->on_hand < (int) $it->min_stock);

            $data['stats'] = [
                'total_items'     => $this->partsInHubScope()->where('is_active', true)->count(),
                'total_machines'  => Machine::where('status', '<>', 'Inactive')->count(),
                'open_defectives' => DefectivePart::count(),
                'low_stock'       => $lowStock->count(),
            ];
            $data['low_stock'] = $lowStock
                ->sortBy(fn ($it) => (int) $it->min_stock > 0 ? $it->on_hand / (int) $it->min_stock : 0)
                ->take(12)
                ->values();
        } else {
            // Any role added later lands here: the original generic summary.
            $data['stats'] = [
                'total_users'          => User::where('is_active', true)->count(),
                'total_items'          => $this->partsInHubScope()->where('is_active', true)->count(),
                'total_machines'       => Machine::where('status', '<>', 'Inactive')->count(),
                'total_parts_in_stock' => $this->stockSnapshot()->where('on_hand', '>=', 1)->count(),
            ];
            $data['part_requests_pending'] = $this->getPendingPartRequests();
            $data['recent_receiving'] = $this->getRecentReceiving();
        }

        return view('dashboard.index', $data + ['isAdminView' => $isAdminView]);
    }

    /**
     * On-hand per active item in the signed-in user's hub, in one pass.
     *
     * Mirrors InventoryController::stocksData() and onHandByLocationSql() —
     * keep the three in sync. Two rules they now share:
     *
     * Units, not rows. A serialised part is one unit per row so counting rows
     * happened to be right for those, but an unserialised row carries its whole
     * quantity, and counting it as 1 reported 5 pieces as 1.
     *
     * Hub-limited. receiving carries hub_id; part_requests does not, so a
     * request is attributed to the hub of whoever raised it.
     *
     * This also replaces the previous per-item query loop, which ran 3 queries
     * for every item in the catalogue.
     */
    private function stockSnapshot()
    {
        $items = $this->partsInHubScope()
            ->where('is_active', true)
            ->select('item_parts.id', 'item_parts.part_no', 'item_parts.description', 'item_parts.min_stock')
            ->get();

        $sumReceiving = fn ($status) => $this->applyHubScope(DB::table('receiving'), 'receiving.hub_id')
            ->where('status', $status)
            ->groupBy('item_part_id')
            ->selectRaw('item_part_id as k, SUM(quantity) as q')
            ->pluck('q', 'k');

        $rec = $sumReceiving('Receiving');
        $ret = $sumReceiving('Return');
        $out = $this->applyRequesterHubScope(DB::table('part_requests'))
            ->where('status', 'Out')
            ->groupBy('item_part_id')
            ->selectRaw('item_part_id as k, SUM(quantity) as q')
            ->pluck('q', 'k');

        return $items->map(function ($it) use ($rec, $ret, $out) {
            $it->on_hand = (int) ($rec[$it->id] ?? 0)
                + (int) ($ret[$it->id] ?? 0)
                - (int) ($out[$it->id] ?? 0);
            return $it;
        });
    }

    /** Active contracts ending within the next 30 days, soonest first. */
    private function expiringContracts()
    {
        $today = now()->startOfDay();
        $limit = now()->addDays(30)->endOfDay();

        return MaintenanceContract::with('customer')
            ->where('is_active', true)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today, $limit])
            ->orderBy('end_date')
            ->get()
            ->map(function ($c) use ($today) {
                $c->days_left = (int) $today->diffInDays($c->end_date, false);
                return $c;
            });
    }

    /** Inbound (received) vs Outbound (issued) counts per month, last 6 months. */
    private function monthlyFlow()
    {
        $from = now()->subMonths(5)->startOfMonth();

        // Scoped so the chart shows this user's branch, not the company.
        $inbound = $this->applyHubScope(DB::table('receiving'), 'receiving.hub_id')
            ->where('status', 'Receiving')
            ->whereNotNull('date_received')
            ->where('date_received', '>=', $from)
            ->groupBy(DB::raw("FORMAT(date_received, 'yyyy-MM')"))
            ->selectRaw("FORMAT(date_received, 'yyyy-MM') as ym, COUNT(*) as c")
            ->pluck('c', 'ym');

        // part_requests has no hub of its own - attributed to the requester's.
        $outbound = $this->applyRequesterHubScope(DB::table('part_requests'))
            ->where('status', 'Out')
            ->whereNotNull('out_date')
            ->where('out_date', '>=', $from)
            ->groupBy(DB::raw("FORMAT(out_date, 'yyyy-MM')"))
            ->selectRaw("FORMAT(out_date, 'yyyy-MM') as ym, COUNT(*) as c")
            ->pluck('c', 'ym');

        return collect(range(5, 0))->map(function ($i) use ($inbound, $outbound) {
            $m = now()->subMonths($i);
            $key = $m->format('Y-m');
            return [
                'm'   => $m->format('M'),
                'in'  => (int) ($inbound[$key] ?? 0),
                'out' => (int) ($outbound[$key] ?? 0),
            ];
        })->values();
    }

    /** Machine-status distribution (top 4 + Other), excluding soft-deleted rows. */
    private function machineStatus()
    {
        $rows = $this->applyHubScope(DB::table('machines'), 'machines.hub_id')
            ->where('status', '<>', 'Inactive')
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as c')
            ->orderByDesc('c')
            ->get();

        // Keys must stay 'label'/'val' — the donut script in dashboard/index.blade.php
        // reads d.label and d.val directly off this payload.
        $total = $rows->sum('c');
        $data = $rows->take(4)->map(fn ($r) => ['label' => $r->status ?: 'Unknown', 'val' => (int) $r->c])->values();
        $other = $rows->skip(4)->sum('c');

        if ($other > 0) {
            $data->push(['label' => 'Other', 'val' => (int) $other]);
        }

        return ['data' => $data, 'total' => (int) $total];
    }

    private function getRecentReceiving()
    {
        return Receiving::with(['itemPart', 'location', 'supplier'])
            ->latest()
            ->limit(10)
            ->get();
    }

    private function getPendingPartRequests()
    {
        return PartRequest::with(['itemPart', 'customer', 'location'])
            ->where('status', 'Pending')
            ->latest()
            ->limit(10)
            ->get();
    }
}
