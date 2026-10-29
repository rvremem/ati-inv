@extends('layouts.app')

@section('title', 'Dashboard')

@section('styles')
<style>
    .page-header { margin-bottom: 18px; padding-top: 8px; }
    .page-header h4 { font-weight: 700; color: #0f172a; margin: 0; }
    .page-header .date-badge { background: #fff; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.8rem; padding: 6px 14px; border-radius: 20px; font-weight: 500; }

    /* KPI tiles */
    .kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 15px 16px; box-shadow: 0 1px 3px rgba(15,23,42,.06); position: relative; overflow: hidden; transition: box-shadow .2s, transform .2s; height: 100%; text-decoration: none; display: block; }
    .kpi:hover { box-shadow: 0 8px 24px rgba(15,23,42,.10); transform: translateY(-2px); }
    .kpi .ico { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; font-size: 1rem; margin-bottom: 12px; }
    .kpi .val { font-size: 1.7rem; font-weight: 760; line-height: 1; letter-spacing: -.02em; color: #0f172a; font-variant-numeric: tabular-nums; }
    .kpi .lbl { font-size: 0.76rem; color: #64748b; margin-top: 5px; font-weight: 550; }
    .kpi .stripe { position: absolute; left: 0; top: 0; bottom: 0; width: 3px; }
    .i-indigo { background: #ede9fe; color: #4f46e5; }
    .i-sky    { background: #e0f2fe; color: #0284c7; }
    .i-emerald{ background: #d1fae5; color: #059669; }
    .i-amber  { background: #fef3c7; color: #b45309; }
    .i-crit   { background: #fee2e2; color: #dc2626; }
    .s-warn { background: #f59e0b; } .s-crit { background: #ef4444; }

    .dash-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 1px 3px rgba(15,23,42,.06); overflow: hidden; height: auto; }
    .dash-card .hd { padding: 15px 18px; border-bottom: 1px solid #eef2f7; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .dash-card .hd .t { font-size: 0.92rem; font-weight: 650; color: #0f172a; display: flex; align-items: center; gap: 8px; }
    .dash-card .hd .t .dot { width: 8px; height: 8px; border-radius: 50%; }
    .dash-card .hd .sub { font-size: 0.74rem; color: #94a3b8; font-weight: 500; }
    .dash-card .hd .lk { font-size: 0.76rem; color: #4f46e5; text-decoration: none; font-weight: 600; }
    .dash-card .hd .lk:hover { text-decoration: underline; }
    .dash-card .bd { padding: 16px 18px; }
    .eyebrow { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: #94a3b8; margin: 24px 2px 12px; }

    .legend { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 12px; }
    .legend span { display: inline-flex; align-items: center; gap: 7px; font-size: 0.78rem; color: #64748b; }
    .legend span i { width: 11px; height: 11px; border-radius: 3px; }
    .dash-card svg { display: block; width: 100%; height: auto; }
    #machineChart { width: min(550px, 100%); height: auto; margin: 6px auto 18px; }
    .dlegend { display: flex; flex-direction: column; gap: 9px; }
    .dlegend .litem { display: flex; align-items: center; gap: 9px; font-size: 0.82rem; }
    .dlegend .litem i { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
    .dlegend .litem .nm { color: #0f172a; }
    .dlegend .litem .vv { margin-left: auto; color: #64748b; font-weight: 650; font-variant-numeric: tabular-nums; }

    .d-tbl { width: 100%; border-collapse: collapse; }
    .d-tbl th { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; font-weight: 650; text-align: left; padding: 9px 18px; background: #f8fafc; border-bottom: 1px solid #eef2f7; }
    .d-tbl td { padding: 11px 18px; font-size: 0.84rem; border-bottom: 1px solid #eef2f7; vertical-align: middle; color: #334155; }
    .d-tbl tr:last-child td { border-bottom: none; }
    .d-tbl tr:hover td { background: #f8fafc; }
    .pn { font-weight: 650; color: #0f172a; }
    .desc { font-size: 0.74rem; color: #94a3b8; }
    .mono { font-variant-numeric: tabular-nums; }
    .pill { font-size: 0.72rem; font-weight: 650; padding: 3px 9px; border-radius: 20px; display: inline-block; }
    .pill.warn { background: #fef3c7; color: #92400e; }
    .pill.crit { background: #fee2e2; color: #991b1b; }
    .pill.mris { background: #ede9fe; color: #4f46e5; }
    .qty { background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.76rem; font-weight: 650; padding: 2px 9px; border-radius: 20px; }
    .barmini { height: 6px; border-radius: 4px; background: #e2e8f0; overflow: hidden; width: 72px; display: inline-block; vertical-align: middle; }
    .barmini > b { display: block; height: 100%; border-radius: 4px; }
    .empty-cell { text-align: center; color: #94a3b8; padding: 28px 12px; font-size: 0.85rem; }

    #dtt { position: fixed; pointer-events: none; z-index: 1080; background: #0f172a; color: #fff; font-size: 0.76rem; font-weight: 550; padding: 6px 10px; border-radius: 8px; opacity: 0; transform: translate(-50%, -125%); transition: opacity .12s; white-space: nowrap; }
    #dtt.on { opacity: 1; }

    /* legacy sections (engineer / other roles) */
    .stat-card { border-radius: 12px; padding: 20px 24px; display: flex; align-items: center; gap: 16px; border: 1px solid #e2e8f0; background: #fff; }
    .stat-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
    .stat-value { font-size: 1.75rem; font-weight: 700; color: #0f172a; line-height: 1; margin-bottom: 4px; }
    .stat-label { font-size: 0.8rem; color: #64748b; font-weight: 500; }
    .section-card { border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; overflow: hidden; }
    .section-card .section-header { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
    .section-card .section-title { font-size: 0.9rem; font-weight: 600; color: #0f172a; display: flex; align-items: center; gap: 8px; }
    .section-card .section-title .dot { width: 8px; height: 8px; border-radius: 50%; }
    .req-summary-chip { font-size: 0.72rem; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
    .chip-pending { background: #fef3c7; color: #92400e; } .chip-out { background: #d1fae5; color: #065f46; } .chip-other { background: #f1f5f9; color: #475569; }
    .empty-state { padding: 48px 20px; text-align: center; }
    .empty-state .empty-icon { width: 56px; height: 56px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; color: #94a3b8; font-size: 1.3rem; }
    .empty-state p { color: #94a3b8; font-size: 0.875rem; margin: 0; }
    .part-no { font-weight: 600; color: #0f172a; font-size: 0.825rem; } .part-desc { font-size: 0.75rem; color: #94a3b8; }
</style>
@endsection

@section('content')
<div class="container-fluid">

    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h4>Dashboard</h4>
            <p class="text-muted mb-0" style="font-size:0.875rem;">Welcome back, <strong>{{ Auth::user()->name }}</strong></p>
        </div>
        <span class="date-badge"><i class="fas fa-calendar-alt me-1"></i>{{ date('l, F j, Y') }}</span>
    </div>

    @if($isAdminView)
        {{-- ===================== SA / DEVELOPER / ADMIN COCKPIT ===================== --}}
        <div class="row g-3">
            <div class="col-md-2 col-sm-4 col-6">
                <a href="{{ route('inventory.items') }}" class="kpi">
                    <div class="ico i-indigo"><i class="fas fa-boxes"></i></div>
                    <div class="val">{{ number_format($stats['total_items']) }}</div>
                    <div class="lbl">Active Items</div>
                </a>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <a href="{{ route('inventory.stocks') }}" class="kpi">
                    <span class="stripe s-crit"></span>
                    <div class="ico i-crit"><i class="fas fa-triangle-exclamation"></i></div>
                    <div class="val">{{ number_format($stats['low_stock']) }}</div>
                    <div class="lbl">Low Stock Items</div>
                </a>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <a href="{{ route('machines.index') }}" class="kpi">
                    <div class="ico i-sky"><i class="fas fa-server"></i></div>
                    <div class="val">{{ number_format($stats['total_machines']) }}</div>
                    <div class="lbl">Machines</div>
                </a>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <a href="{{ route('requests.pending') }}" class="kpi">
                    <span class="stripe s-warn"></span>
                    <div class="ico i-amber"><i class="fas fa-hourglass-half"></i></div>
                    <div class="val">{{ number_format($stats['pending_requests']) }}</div>
                    <div class="lbl">Pending Requests</div>
                </a>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <a href="{{ route('maintenance.report') }}" class="kpi">
                    <span class="stripe s-warn"></span>
                    <div class="ico i-amber"><i class="fas fa-file-contract"></i></div>
                    <div class="val">{{ number_format($stats['expiring_contracts']) }}</div>
                    <div class="lbl">Contracts Expiring (≤30d)</div>
                </a>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <a href="{{ route('defective-parts.index') }}" class="kpi">
                    <div class="ico i-emerald"><i class="fas fa-screwdriver-wrench"></i></div>
                    <div class="val">{{ number_format($stats['open_defectives']) }}</div>
                    <div class="lbl">Defective Items</div>
                </a>
            </div>
        </div>

        <div class="eyebrow">Overview</div>
        <div class="row g-3">
            <div class="col-lg-7 d-flex flex-column">
                <div class="dash-card">
                    <div class="hd">
                        <div class="t"><span class="dot" style="background:#4f46e5"></span>Receiving, Old Stock &amp; Outbound</div>
                        <div class="sub">Last 6 months</div>
                    </div>
                    <div class="bd">
                        <svg id="flowChart" viewBox="0 0 560 250" role="img" aria-label="Receiving, old stock and outbound per month"></svg>
                        <div class="legend">
                            <span><i style="background:#10b981"></i>Receiving</span>
                            <span><i style="background:#f59e0b"></i>Old Stock</span>
                            <span><i style="background:#4f46e5"></i>Outbound (issued)</span>
                        </div>
                    </div>
                </div>

                <div class="dash-card mt-3 flex-grow-1">
                    <div class="hd">
                        <div class="t"><span class="dot" style="background:#f59e0b"></span>Pending Part Requests
                            @if($part_requests_pending->count() > 0)<span class="pill warn" style="margin-left:4px">{{ $part_requests_pending->count() }}</span>@endif
                        </div>
                        <a href="{{ route('requests.pending') }}" class="lk">View all →</a>
                    </div>
                    <div style="overflow-x:auto;">
                    <table class="d-tbl">
                        <thead><tr><th>Pick date</th><th>MRIS</th><th>Customer</th><th>Item</th><th>Qty</th><th>Requested by</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($part_requests_pending as $req)
                                <tr>
                                    <td class="mono" style="white-space:nowrap;">{{ $req->pick_date ? $req->pick_date->format('M d, Y') : '—' }}</td>
                                    <td>@if($req->mris_no)<span class="pill mris">{{ $req->mris_no }}</span>@else<span class="text-muted">—</span>@endif</td>
                                    <td>{{ $req->customer->name ?? '—' }}</td>
                                    <td><div class="pn">{{ $req->itemPart->part_no ?? $req->part_no }}</div><div class="desc">{{ Str::limit($req->itemPart->description ?? '—', 30) }}</div></td>
                                    <td><span class="qty">{{ $req->quantity }}</span></td>
                                    <td>{{ $req->requestedBy->name ?? $req->user_id ?? '—' }}</td>
                                    <td><span class="pill warn">{{ $req->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-cell">No pending part requests</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>

            </div>

            <div class="col-lg-5 d-flex flex-column">
                <div class="dash-card">
                    <div class="hd">
                        <div class="t"><span class="dot" style="background:#0ea5e9"></span>Machine Status</div>
                        <div class="sub">{{ number_format($machine_status['total']) }} total</div>
                    </div>
                    <div class="bd">
                        <svg id="machineChart" viewBox="0 0 180 180" role="img" aria-label="Machine status distribution"></svg>
                        <div class="dlegend" id="machineLegend"></div>
                    </div>
                </div>

                <div class="dash-card mt-3 flex-grow-1">
                    <div class="hd">
                        <div class="t"><span class="dot" style="background:#ef4444"></span>Low Stock Alerts</div>
                        <a href="{{ route('inventory.stocks') }}" class="lk">Stock levels →</a>
                    </div>
                    <table class="d-tbl">
                        <thead><tr><th>Part</th><th>On hand</th><th>Level</th></tr></thead>
                        <tbody>
                            @forelse($low_stock as $it)
                                @php
                                    $min = (int) $it->min_stock;
                                    $ratio = $min > 0 ? max(0, min(100, ($it->on_hand / $min) * 100)) : 0;
                                    $crit = $it->on_hand <= 0 || $ratio <= 25;
                                @endphp
                                <tr>
                                    <td><div class="pn">{{ $it->part_no }}</div><div class="desc">{{ Str::limit($it->description, 34) }}</div></td>
                                    <td><span class="pill {{ $crit ? 'crit' : 'warn' }}">{{ $it->on_hand }} / {{ $min }}</span></td>
                                    <td><span class="barmini"><b style="width:{{ $ratio }}%;background:{{ $crit ? '#ef4444' : '#f59e0b' }}"></b></span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="empty-cell">No low-stock items 🎉</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    @elseif(auth()->user()->role === 'Engineer')
        {{-- ===================== ENGINEER ===================== --}}
        @php
            $pending_count = $my_requests->where('status', 'Pending')->count();
            $out_count     = $my_requests->where('status', 'Out')->count();
            $other_count   = $my_requests->whereNotIn('status', ['Pending', 'Out'])->count();
        @endphp

        <div class="row g-3 mb-3">
            <div class="col-md-3 col-sm-6">
                <div class="kpi">
                    <div class="ico i-indigo"><i class="fas fa-boxes"></i></div>
                    <div class="val">{{ number_format($stats['total_items']) }}</div>
                    <div class="lbl">Active Items</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="{{ route('requests.machines') }}" class="kpi">
                    <div class="ico i-sky"><i class="fas fa-server"></i></div>
                    <div class="val">{{ number_format($stats['total_machines']) }}</div>
                    <div class="lbl">Machines</div>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="{{ route('maintenance.index') }}" class="kpi">
                    <div class="ico i-emerald"><i class="fas fa-file-contract"></i></div>
                    <div class="val">{{ number_format($stats['maintenance']) }}</div>
                    <div class="lbl">Maintenance</div>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="{{ route('requests.my') }}" class="kpi">
                    <span class="stripe s-warn"></span>
                    <div class="ico i-amber"><i class="fas fa-hourglass-half"></i></div>
                    <div class="val">{{ number_format($stats['pending_own']) }}</div>
                    <div class="lbl">My Pending Requests</div>
                </a>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header">
                <div class="section-title"><span class="dot bg-primary"></span>My Request Status</div>
                @if($my_requests->count() > 0)
                <div class="d-flex gap-2">
                    @if($pending_count)<span class="req-summary-chip chip-pending"><i class="fas fa-clock me-1"></i>{{ $pending_count }} Pending</span>@endif
                    @if($out_count)<span class="req-summary-chip chip-out"><i class="fas fa-check me-1"></i>{{ $out_count }} Out</span>@endif
                    @if($other_count)<span class="req-summary-chip chip-other"><i class="fas fa-circle me-1"></i>{{ $other_count }} Other</span>@endif
                </div>
                @endif
            </div>
            @if($my_requests->count() > 0)
                <div class="table-responsive px-0">
                    <table class="table dash-table mb-0 no-datatable">
                        <thead><tr><th>Part No</th><th>Description</th><th>Client</th><th>Pick Date</th><th class="text-center">Qty</th><th>MRIS</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach($my_requests as $req)
                                <tr>
                                    <td><span class="part-no">{{ $req->itemPart->part_no ?? $req->part_no }}</span></td>
                                    <td class="part-desc">{{ Str::limit($req->itemPart->description ?? '—', 35) }}</td>
                                    <td>{{ $req->customer->name ?? '—' }}</td>
                                    <td style="white-space:nowrap;font-size:0.8rem;">{{ $req->pick_date ? $req->pick_date->format('M d, Y') : '—' }}</td>
                                    <td class="text-center"><span class="qty">{{ $req->quantity }}</span></td>
                                    <td>@if($req->mris_no)<span class="pill mris">{{ $req->mris_no }}</span>@else<span class="text-muted">—</span>@endif</td>
                                    <td><x-status-badge :status="$req->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state"><div class="empty-icon"><i class="fas fa-clipboard-list"></i></div><p>No requests submitted yet</p></div>
            @endif
        </div>

    {{-- ServiceDesk is this app's name for the role atiis calls Maintenance;
         the branch must match what DashboardController::index() keyed on, or
         the role falls through to the generic block and its stats keys. --}}
    @elseif(auth()->user()->role === 'ServiceDesk')
        {{-- ===================== SERVICE DESK ===================== --}}
        <div class="row g-3 mb-3">
            <div class="col-md-4 col-sm-6">
                <a href="{{ route('maintenance.report') }}" class="kpi">
                    <span class="stripe s-warn"></span>
                    <div class="ico i-amber"><i class="fas fa-file-contract"></i></div>
                    <div class="val">{{ number_format($stats['expiring_soon']) }}</div>
                    <div class="lbl">Contracts Expiring (≤30d)</div>
                </a>
            </div>
            <div class="col-md-4 col-sm-6">
                <a href="{{ route('maintenance.index') }}" class="kpi">
                    <div class="ico i-indigo"><i class="fas fa-file-contract"></i></div>
                    <div class="val">{{ number_format($stats['contracts']) }}</div>
                    <div class="lbl">Active Contracts</div>
                </a>
            </div>
        </div>

        <div class="dash-card">
            <div class="hd">
                <div class="t"><span class="dot" style="background:#f59e0b"></span>Contracts Expiring Soon</div>
                <a href="{{ route('maintenance.report') }}" class="lk">Expiry report →</a>
            </div>
            <table class="d-tbl">
                <thead><tr><th>Client</th><th>Serial</th><th>End Date</th><th>Ends in</th></tr></thead>
                <tbody>
                    @forelse($expiring_contracts as $c)
                        <tr>
                            <td class="pn">{{ Str::limit($c->customer->name ?? ($c->customer->name ?? $c->company) ?? '—', 40) }}</td>
                            <td class="mono">{{ $c->serial_no ?? '—' }}</td>
                            <td class="mono">{{ $c->end_date ? $c->end_date->format('M d, Y') : '—' }}</td>
                            <td><span class="pill {{ $c->days_left <= 14 ? 'crit' : 'warn' }}">{{ $c->days_left }} day{{ $c->days_left == 1 ? '' : 's' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-cell">No contracts expiring in the next 30 days 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif(auth()->user()->role === 'PartsCenter')
        {{-- ===================== PARTS CENTER ===================== --}}
        <div class="row g-3 mb-3">
            <div class="col-md-3 col-sm-6">
                <div class="kpi">
                    <div class="ico i-indigo"><i class="fas fa-boxes"></i></div>
                    <div class="val">{{ number_format($stats['total_items']) }}</div>
                    <div class="lbl">Active Parts</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="{{ route('reports.machines') }}" class="kpi">
                    <div class="ico i-sky"><i class="fas fa-server"></i></div>
                    <div class="val">{{ number_format($stats['total_machines']) }}</div>
                    <div class="lbl">Machines</div>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="{{ route('reports.defective') }}" class="kpi">
                    <div class="ico i-emerald"><i class="fas fa-screwdriver-wrench"></i></div>
                    <div class="val">{{ number_format($stats['open_defectives']) }}</div>
                    <div class="lbl">Defective Items</div>
                </a>
            </div>
            <div class="col-md-3 col-sm-6">
                <a href="{{ route('reports.stock') }}" class="kpi">
                    <span class="stripe s-crit"></span>
                    <div class="ico i-crit"><i class="fas fa-triangle-exclamation"></i></div>
                    <div class="val">{{ number_format($stats['low_stock']) }}</div>
                    <div class="lbl">Critical Stocks</div>
                </a>
            </div>
        </div>

        <div class="dash-card">
            <div class="hd">
                <div class="t"><span class="dot" style="background:#ef4444"></span>Critical Stock Alerts</div>
                <a href="{{ route('reports.stock') }}" class="lk">Stock levels →</a>
            </div>
            <table class="d-tbl">
                <thead><tr><th>Part</th><th>On hand</th><th>Level</th></tr></thead>
                <tbody>
                    @forelse($low_stock as $it)
                        @php
                            $min = (int) $it->min_stock;
                            $ratio = $min > 0 ? max(0, min(100, ($it->on_hand / $min) * 100)) : 0;
                            $crit = $it->on_hand <= 0 || $ratio <= 25;
                        @endphp
                        <tr>
                            <td><div class="pn">{{ $it->part_no }}</div><div class="desc">{{ Str::limit($it->description, 44) }}</div></td>
                            <td><span class="pill {{ $crit ? 'crit' : 'warn' }}">{{ $it->on_hand }} / {{ $min }}</span></td>
                            <td><span class="barmini"><b style="width:{{ $ratio }}%;background:{{ $crit ? '#ef4444' : '#f59e0b' }}"></b></span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-cell">No critical-stock items 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @else
        {{-- ===================== PARTSCENTER / OTHER ===================== --}}
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="stat-card"><div class="stat-icon i-indigo"><i class="fas fa-boxes"></i></div><div><div class="stat-value">{{ number_format($stats['total_items']) }}</div><div class="stat-label">Active Items</div></div></div></div>
            <div class="col-md-4"><div class="stat-card"><div class="stat-icon i-amber"><i class="fas fa-server"></i></div><div><div class="stat-value">{{ number_format($stats['total_machines']) }}</div><div class="stat-label">Machines</div></div></div></div>
            <div class="col-md-4"><div class="stat-card"><div class="stat-icon i-emerald"><i class="fas fa-layer-group"></i></div><div><div class="stat-value">{{ number_format($stats['total_parts_in_stock']) }}</div><div class="stat-label">Parts in Stock</div></div></div></div>
        </div>
        <div class="section-card">
            <div class="section-header">
                <div class="section-title"><span class="dot bg-warning"></span>Pending Part Requests
                    @if($part_requests_pending->count() > 0)<span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem;">{{ $part_requests_pending->count() }}</span>@endif
                </div>
                <a href="{{ route('requests.pending') }}" style="font-size:0.78rem;color:#4f46e5;text-decoration:none;font-weight:500;">View all →</a>
            </div>
            @if($part_requests_pending->count() > 0)
                <div class="table-responsive px-0">
                    <table class="table dash-table mb-0 no-datatable">
                        <thead><tr><th>Pick Date</th><th>Customer</th><th>Item</th><th>Qty</th><th>Requested By</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach($part_requests_pending as $req)
                                <tr>
                                    <td style="white-space:nowrap;">{{ $req->pick_date ? $req->pick_date->format('M d, Y') : '—' }}</td>
                                    <td>{{ $req->customer->name ?? '—' }}</td>
                                    <td><div class="part-no">{{ $req->itemPart->part_no ?? $req->part_no }}</div><div class="part-desc">{{ Str::limit($req->itemPart->description ?? '—', 30) }}</div></td>
                                    <td><span class="qty">{{ $req->quantity }}</span></td>
                                    <td>{{ $req->requestedBy->name ?? $req->user_id ?? '—' }}</td>
                                    <td><span class="pill warn">{{ $req->status }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state"><div class="empty-icon"><i class="fas fa-check-double"></i></div><p>No pending part requests</p></div>
            @endif
        </div>
    @endif

</div>

@if($isAdminView)
<div id="dtt"></div>
<script>
(function () {
    const flow = @json($monthly_flow);
    const machines = @json($machine_status['data']);
    const SVGNS = 'http://www.w3.org/2000/svg';
    const el = (n, a) => { const e = document.createElementNS(SVGNS, n); for (const k in a) e.setAttribute(k, a[k]); return e; };
    const tt = document.getElementById('dtt');
    const showTT = (e, h) => { tt.innerHTML = h; tt.style.left = e.clientX + 'px'; tt.style.top = e.clientY + 'px'; tt.classList.add('on'); };
    const moveTT = e => { tt.style.left = e.clientX + 'px'; tt.style.top = e.clientY + 'px'; };
    const hideTT = () => tt.classList.remove('on');
    const DCOL = ['#4f46e5', '#10b981', '#0ea5e9', '#f59e0b', '#94a3b8'];

    // ---- grouped bar chart ----
    (function () {
        const svg = document.getElementById('flowChart');
        if (!svg || !flow.length) return;
        const W = 560, H = 250, padL = 38, padR = 8, padT = 14, padB = 26;
        const plotW = W - padL - padR, plotH = H - padT - padB, base = padT + plotH;
        let max = Math.max(1, ...flow.map(d => Math.max(d.in, d.old, d.out)));
        const step = Math.pow(10, Math.floor(Math.log10(max)));
        max = Math.ceil(max / step) * step;
        const ticks = 4;
        // Counts are whole numbers: below 4 the ticks would be fractions that
        // round to a repeated 0/1 axis, so stretch to one unit per tick.
        max = Math.max(max, ticks);
        for (let i = 0; i <= ticks; i++) {
            const v = max / ticks * i, y = base - (v / max) * plotH;
            svg.appendChild(el('line', { x1: padL, y1: y, x2: W - padR, y2: y, stroke: '#eef2f7', 'stroke-width': 1 }));
            const t = el('text', { x: padL - 8, y: y + 3, 'text-anchor': 'end', fill: '#94a3b8', 'font-size': 11 }); t.textContent = Math.round(v); svg.appendChild(t);
        }
        const slot = plotW / flow.length, bw = 17, gap = 3;
        flow.forEach((d, i) => {
            const cx = padL + slot * i + slot / 2;
            const mk = (val, x, color, label) => {
                const h = (val / max) * plotH;
                const r = el('rect', { x, y: base - h, width: bw, height: Math.max(h, 0), rx: 4, fill: color, style: 'cursor:pointer' });
                r.addEventListener('mouseenter', e => showTT(e, '<b>' + d.m + '</b> · ' + label + ' <b>' + val + '</b>'));
                r.addEventListener('mousemove', moveTT); r.addEventListener('mouseleave', hideTT);
                svg.appendChild(r);
            };
            mk(d.in, cx - bw * 1.5 - gap, '#10b981', 'Receiving');
            mk(d.old, cx - bw / 2, '#f59e0b', 'Old Stock');
            mk(d.out, cx + bw / 2 + gap, '#4f46e5', 'Outbound');
            const lx = el('text', { x: cx, y: base + 17, 'text-anchor': 'middle', fill: '#94a3b8', 'font-size': 11 }); lx.textContent = d.m; svg.appendChild(lx);
        });
    })();

    // ---- donut ----
    (function () {
        const svg = document.getElementById('machineChart'), lg = document.getElementById('machineLegend');
        if (!svg || !machines.length) return;
        const cx = 90, cy = 90, r = 40, sw = 24, C = 2 * Math.PI * r;
        const total = machines.reduce((s, d) => s + d.val, 0) || 1;
        svg.appendChild(el('circle', { cx, cy, r, fill: 'none', stroke: '#eef2f7', 'stroke-width': sw }));
        let off = 0;
        machines.forEach((d, i) => {
            const len = (d.val / total) * C, color = DCOL[i % DCOL.length];
            const seg = el('circle', { cx, cy, r, fill: 'none', stroke: color, 'stroke-width': sw,
                'stroke-dasharray': (len - 2) + ' ' + (C - len + 2), 'stroke-dashoffset': -off,
                transform: 'rotate(-90 ' + cx + ' ' + cy + ')', style: 'cursor:pointer' });
            const pct = Math.round(d.val / total * 100);
            seg.addEventListener('mouseenter', e => showTT(e, '<b>' + d.label + '</b> · ' + d.val + ' (' + pct + '%)'));
            seg.addEventListener('mousemove', moveTT); seg.addEventListener('mouseleave', hideTT);
            svg.appendChild(seg); off += len;
            const row = document.createElement('div'); row.className = 'litem';
            row.innerHTML = '<i style="background:' + color + '"></i><span class="nm">' + d.label + '</span><span class="vv">' + d.val.toLocaleString() + '</span>';
            lg.appendChild(row);
        });
        const t1 = el('text', { x: cx, y: cy - 1, 'text-anchor': 'middle', fill: '#0f172a', 'font-weight': 760, 'font-size': 13 }); t1.textContent = total.toLocaleString();
        const t2 = el('text', { x: cx, y: cy + 10, 'text-anchor': 'middle', fill: '#94a3b8', 'font-size': 7.5 }); t2.textContent = 'machines';
        svg.appendChild(t1); svg.appendChild(t2);
    })();
})();
</script>
@endif
@endsection
