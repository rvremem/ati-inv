<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - ATI Inventory</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --sidebar-bg: #0f172a;
            --sidebar-text: #94a3b8;
            --sidebar-text-active: #f1f5f9;
            --sidebar-header: #475569;
            --sidebar-active-bg: rgba(79, 70, 229, 0.15);
            --sidebar-active-border: #4f46e5;
            --sidebar-hover-bg: rgba(255,255,255,0.05);
            --navbar-bg: #0f172a;
            --body-bg: #f1f5f9;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--body-bg);
        }

        /* Navbar */
        .navbar {
            background-color: var(--navbar-bg) !important;
            box-shadow: 0 1px 0 rgba(255,255,255,0.06);
            height: 56px;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.3px;
        }

        .navbar-brand .brand-icon {
            background: var(--primary-color);
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }

        .nav-link {
            color: #94a3b8 !important;
            font-size: 0.875rem;
        }

        .nav-link:hover { color: white !important; }

        .dropdown-menu {
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-radius: 10px;
            padding: 8px;
        }

        .dropdown-item {
            border-radius: 6px;
            font-size: 0.875rem;
            padding: 8px 12px;
        }

        .dropdown-item:hover { background-color: #f1f5f9; }

        /* Sidebar */
        .sidebar {
            background-color: var(--sidebar-bg);
            height: calc(100vh - 56px);
            position: fixed;
            left: 0;
            top: 56px;
            width: 250px;
            overflow-y: auto;
            overflow-x: hidden;
            color: var(--sidebar-text);
            border-right: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 20px;
        }

        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
        .sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        /* Sidebar user card */
        .sidebar-user {
            padding: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }

        .sidebar-user-info { overflow: hidden; }

        .sidebar-user-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: #f1f5f9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: 0.7rem;
            color: var(--sidebar-text);
        }

        /* Sidebar menu */
        .sidebar-menu {
            list-style: none;
            padding: 8px 0;
            margin: 0;
        }

        .sidebar-section-label {
            padding: 16px 16px 6px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--sidebar-header);
        }

        .sidebar-menu li { margin: 1px 8px; }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.845rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }

        .sidebar-menu a:hover {
            background-color: var(--sidebar-hover-bg);
            color: var(--sidebar-text-active);
        }

        .sidebar-menu a.active {
            background-color: var(--sidebar-active-bg);
            color: var(--sidebar-text-active);
            border-left: 3px solid var(--sidebar-active-border);
            padding-left: 9px;
        }

        .sidebar-menu a .nav-icon {
            width: 18px;
            text-align: center;
            font-size: 0.85rem;
            flex-shrink: 0;
            opacity: 0.8;
        }

        .sidebar-menu a.active .nav-icon { opacity: 1; color: var(--primary-color); }

        /* Main content */
        .main-content {
            margin-left: 250px;
            padding: 16px 24px 24px;
            min-height: calc(100vh - 56px);
        }

        /* Cards */
        .card {
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            border-radius: 10px;
        }

        .card-header {
            border-radius: 10px 10px 0 0 !important;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Stat boxes */
        .stat-box {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4338ca 100%);
            color: white;
            padding: 22px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-box.info    { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); }
        .stat-box.success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-box.warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-box.danger  { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

        .stat-box h3 { font-size: 2rem; font-weight: 700; margin: 10px 0; }
        .stat-box p  { font-size: 0.875rem; margin: 0; opacity: 0.9; }

        .table-responsive { border-radius: 8px; overflow-x: visible; }

        .badge { padding: 5px 10px; }

        .badge-critical { background-color: #ef4444; }
        .badge-normal   { background-color: #10b981; }
        .badge-pending  { background-color: #f59e0b; color: #000; }
        .badge-approved { background-color: #0ea5e9; }

        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
        .btn-primary:hover { background-color: var(--primary-hover); border-color: var(--primary-hover); }

        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; top: 0; min-height: auto; }
            .main-content { margin-left: 0; margin-top: 0; }
            .navbar-brand { font-size: 1rem; }
        }

        /* === Global Page Styles === */

        /* Page header pattern used across all pages */
        .page-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .page-header-bar h2, .page-header-bar h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        /* All page-level h2 headings */
        .main-content h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
        }

        /* Tables */
        .table {
            font-size: 0.845rem;
            color: #334155;
        }

        .table thead th {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 14px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 11px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover td { background: #f8fafc; }
        .table-bordered td, .table-bordered th { border-color: #e2e8f0; }

        /* Buttons */
        .btn { font-size: 0.845rem; font-weight: 500; border-radius: 8px; padding: 7px 16px; }
        .btn-sm { font-size: 0.78rem; padding: 5px 11px; border-radius: 6px; }
        .btn-primary { background: #4f46e5; border-color: #4f46e5; }
        .btn-primary:hover { background: #4338ca; border-color: #4338ca; }
        .btn-success { background: #10b981; border-color: #10b981; }
        .btn-success:hover { background: #059669; border-color: #059669; }
        .btn-warning { background: #f59e0b; border-color: #f59e0b; color: #fff; }
        .btn-warning:hover { background: #d97706; border-color: #d97706; color: #fff; }
        .btn-danger { background: #ef4444; border-color: #ef4444; }
        .btn-danger:hover { background: #dc2626; border-color: #dc2626; }
        .btn-secondary { background: #64748b; border-color: #64748b; color: #fff; }
        .btn-secondary:hover { background: #475569; border-color: #475569; color: #fff; }

        /* Back navigation, used through the back-link component.
           The component name is written without angle brackets on purpose:
           Blade parses component tags even inside a CSS comment, and spelling
           it out here compiled into a real, unclosed component call.

           Deliberately quiet styling: leaving a page is never the action a
           screen is there for, so it reads as an outline rather than competing
           with the Save and Export buttons it shares a page with. */
        .page-back {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #eef0f3;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 18px;
            font-size: 13.5px;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            transition: border-color .15s, color .15s, background-color .15s;
        }

        .btn-back:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .btn-back:focus-visible {
            outline: 2px solid #4f46e5;
            outline-offset: 2px;
        }

        /* Forms */
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        .form-control, .form-select {
            font-size: 0.845rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 12px;
            color: #0f172a;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }

        .form-control-sm, .form-select-sm {
            font-size: 0.78rem;
            padding: 5px 10px;
            border-radius: 6px;
        }

        .form-check-input:checked {
            background-color: #4f46e5;
            border-color: #4f46e5;
        }

        .invalid-feedback { font-size: 0.78rem; }

        /* === Table Action Buttons === */
        .btn-group-sm {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .btn-group-sm > .btn,
        .btn-group-sm .btn-sm,
        .btn-group-sm a.btn,
        .btn-group-sm button.btn {
            width: 50px !important;
            height: 30px !important;
            min-width: 50px !important;
            max-width: 50px !important;
            flex: 0 0 50px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 8px !important;
            border: none !important;
            font-size: 0.78rem;
            transition: all 0.15s ease;
            line-height: 1;
        }

        /* Edit — indigo ghost */
        .btn-group-sm .btn-warning {
            background: #ede9fe;
            color: #4f46e5;
            border: none;
        }
        .btn-group-sm .btn-warning:hover {
            background: #4f46e5;
            color: #fff;
        }

        /* Delete — red ghost */
        .btn-group-sm .btn-danger {
            background: #fee2e2;
            color: #ef4444;
            border: none;
        }
        .btn-group-sm .btn-danger:hover {
            background: #ef4444;
            color: #fff;
        }

        /* View/primary — blue ghost */
        .btn-group-sm .btn-primary {
            background: #e0e7ff;
            color: #4f46e5;
            border: none;
        }
        .btn-group-sm .btn-primary:hover {
            background: #4f46e5;
            color: #fff;
        }

        /* Info ghost */
        .btn-group-sm .btn-info {
            background: #e0f2fe;
            color: #0284c7;
            border: none;
        }
        .btn-group-sm .btn-info:hover {
            background: #0284c7;
            color: #fff;
        }

        /* Success ghost */
        .btn-group-sm .btn-success {
            background: #d1fae5;
            color: #059669;
            border: none;
        }
        .btn-group-sm .btn-success:hover {
            background: #059669;
            color: #fff;
        }

        /* Equalise all icons inside action buttons */
        .btn-group-sm .btn i,
        .btn-group-sm a.btn i,
        .btn-group-sm button.btn i {
            font-size: 0.75rem;
            width: 14px;
            text-align: center;
            display: inline-block;
        }

        /* Cards */
        .card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card-header {
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
            padding: 14px 20px;
            font-weight: 700;
            font-size: 0.9rem;
            color: #0f172a;
            border-radius: 12px 12px 0 0 !important;
        }
        .card-header h1, .card-header h2, .card-header h3,
        .card-header h4, .card-header h5, .card-header h6 {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        .card-body { padding: 16px 20px; }
        .card-footer { background: #f8fafc; border-top: 1px solid #f1f5f9; padding: 10px 20px; font-size: 0.8rem; }

        /* Modals */
        .modal-content { border: none; border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .modal-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 14px 14px 0 0;
            padding: 16px 20px;
        }
        .modal-title { font-size: 0.95rem; font-weight: 700; color: #0f172a; }
        .modal-body { padding: 20px; }
        .modal-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 14px 14px; padding: 12px 20px; }

        /* Pagination */
        .pagination { font-size: 0.845rem; }
        .page-link { color: #4f46e5; border-color: #e2e8f0; border-radius: 6px !important; margin: 0 2px; }
        .page-item.active .page-link { background: #4f46e5; border-color: #4f46e5; }
        .page-link:hover { color: #4338ca; background: #ede9fe; border-color: #e2e8f0; }

        /* Badges */
        .badge { font-weight: 600; font-size: 0.72rem; padding: 4px 10px; border-radius: 20px; }

        /* Back button style */
        .btn-back { color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; }
        .btn-back:hover { background: #e2e8f0; color: #0f172a; }
    </style>

    @yield('styles')

    {{-- In <head> so notify() exists before any page script can call it. --}}
    @include('partials.notify')
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <span class="brand-icon"><i class="fas fa-boxes"></i></span>
                ATI Inventory
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    @auth
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> {{ Auth::user()->name }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('users.profile') }}"><i class="fas fa-user-circle"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="fas fa-sign-out-alt"></i> Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    @auth
        <aside class="sidebar">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">{{ Auth::user()->name }}</div>
                    {{-- Display name from the roles table, falling back to the code. --}}
                    <div class="sidebar-user-role">{{ Auth::user()->userRole->name ?? Auth::user()->role }}</div>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li class="sidebar-section-label">Main</li>
                <li><a href="{{ route('dashboard') }}" class="@if(request()->routeIs('dashboard')) active @endif">
                    <span class="nav-icon"><i class="fas fa-chart-line"></i></span> Dashboard
                </a></li>

                @if(Auth::user()->role === 'Engineer')
                    {{-- Items is read-only for Engineers (no Actions column, no
                         Add Item, write endpoints 403) — see canManageItems(). --}}
                    <!-- <li class="sidebar-section-label">Inventory</li>
                    <li><a href="{{ route('inventory.items') }}" class="@if(request()->routeIs('inventory.items*')) active @endif"><span class="nav-icon"><i class="fas fa-boxes"></i></span> Items</a></li> -->

                    <li class="sidebar-section-label">Outbound</li>
                    <li><a href="{{ route('requests.parts') }}" class="@if(request()->routeIs('requests.parts*')) active @endif"><span class="nav-icon"><i class="fas fa-hand-holding"></i></span> Request Parts</a></li>
                    <li><a href="{{ route('requests.machines') }}" class="@if(request()->routeIs('requests.machines*')) active @endif"><span class="nav-icon"><i class="fas fa-dolly"></i></span> Request Machine</a></li>
                    <li><a href="{{ route('requests.machine-parts') }}" class="@if(request()->routeIs('requests.machine-parts*')) active @endif"><span class="nav-icon"><i class="fas fa-tools"></i></span> Request Machine Parts</a></li>
                    <li><a href="{{ route('requests.my') }}" class="@if(request()->routeIs('requests.my')) active @endif"><span class="nav-icon"><i class="fas fa-clock"></i></span> My Requests</a></li>

                    <li class="sidebar-section-label">Maintenance</li>
                    <li><a href="{{ route('maintenance.index') }}" class="@if(request()->routeIs('maintenance.*')) active @endif"><span class="nav-icon"><i class="fas fa-file-contract"></i></span> Contracts</a></li>

                    <li class="sidebar-section-label">Reports</li>
                    <li><a href="{{ route('reports.activity-log') }}" class="@if(request()->routeIs('reports.activity-log')) active @endif"><span class="nav-icon"><i class="fas fa-history"></i></span> Activity Log</a></li>
                {{-- ServiceDesk is this app's name for the role atiis calls
                     Maintenance. Contracts plus the two reports that go with
                     them — no Inventory, Inbound or Outbound. --}}
                @elseif(Auth::user()->role === 'ServiceDesk')
                    <li class="sidebar-section-label">Maintenance</li>
                    <li><a href="{{ route('maintenance.index') }}" class="@if(request()->routeIs('maintenance.index')) active @endif"><span class="nav-icon"><i class="fas fa-file-contract"></i></span> Contracts</a></li>

                    <li class="sidebar-section-label">Reports</li>
                    <li><a href="{{ route('maintenance.report') }}" class="@if(request()->routeIs('maintenance.report')) active @endif"><span class="nav-icon"><i class="fas fa-file-alt"></i></span> Expiring Contracts</a></li>
                    <li><a href="{{ route('reports.activity-log') }}" class="@if(request()->routeIs('reports.activity-log')) active @endif"><span class="nav-icon"><i class="fas fa-history"></i></span> Activity Log</a></li>
                @elseif(Auth::user()->role === 'PartsCenter')
                    <li class="sidebar-section-label">Reports</li>
                    <li><a href="{{ route('reports.index') }}" class="@if(request()->routeIs('reports.*')) active @endif"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Reports</a></li>
                @else
                    <li class="sidebar-section-label">Inventory</li>
                    <li><a href="{{ route('inventory.items') }}" class="@if(request()->routeIs('inventory.items*')) active @endif"><span class="nav-icon"><i class="fas fa-boxes"></i></span> Items</a></li>
                    <li><a href="{{ route('machines.index') }}" class="@if(request()->routeIs('machines.*')) active @endif"><span class="nav-icon"><i class="fas fa-server"></i></span> Machines</a></li>
                    <li><a href="{{ route('inventory.locations') }}" class="@if(request()->routeIs('inventory.locations*')) active @endif"><span class="nav-icon"><i class="fas fa-map-marker-alt"></i></span> Locations</a></li>
                    <li><a href="{{ route('inventory.stocks') }}" class="@if(request()->routeIs('inventory.stocks')) active @endif"><span class="nav-icon"><i class="fas fa-layer-group"></i></span> Stock Levels</a></li>
                    <li><a href="{{ route('inventory.parts-location') }}" class="@if(request()->routeIs('inventory.parts-location*')) active @endif"><span class="nav-icon"><i class="fas fa-map-pin"></i></span> Parts Location</a></li>
                    <li><a href="{{ route('settings.customers') }}" class="@if(request()->routeIs('settings.customers*')) active @endif"><span class="nav-icon"><i class="fas fa-handshake"></i></span> Customers</a></li>
                    <li><a href="{{ route('inventory.suppliers') }}" class="@if(request()->routeIs('inventory.suppliers*')) active @endif"><span class="nav-icon"><i class="fas fa-truck"></i></span> Suppliers</a></li>

                    <li class="sidebar-section-label">Inbound</li>
                    <li><a href="{{ route('receiving.items') }}" class="@if(request()->routeIs('receiving.items*')) active @endif"><span class="nav-icon"><i class="fas fa-download"></i></span> Receive Parts</a></li>
                    <li><a href="{{ route('receiving.machines') }}" class="@if(request()->routeIs('receiving.machines*')) active @endif"><span class="nav-icon"><i class="fas fa-arrow-circle-down"></i></span> Add Machine Parts</a></li>
                    <li><a href="{{ route('receiving.return-parts') }}" class="@if(request()->routeIs('receiving.return-parts*')) active @endif"><span class="nav-icon"><i class="fas fa-undo-alt"></i></span> Return Parts</a></li>
                    <li><a href="{{ route('receiving.return-machines') }}" class="@if(request()->routeIs('receiving.return-machines*')) active @endif"><span class="nav-icon"><i class="fas fa-undo-alt"></i></span> Return Machines</a></li>
                    <li><a href="{{ route('defective-parts.index') }}" class="@if(request()->routeIs('defective-parts.*')) active @endif"><span class="nav-icon"><i class="fas fa-exclamation-triangle"></i></span> Defective Parts</a></li>

                    <li class="sidebar-section-label">Outbound</li>
                    <li><a href="{{ route('requests.parts') }}" class="@if(request()->routeIs('requests.parts*')) active @endif"><span class="nav-icon"><i class="fas fa-hand-holding"></i></span> Request Parts</a></li>
                    <li><a href="{{ route('requests.machines') }}" class="@if(request()->routeIs('requests.machines*')) active @endif"><span class="nav-icon"><i class="fas fa-dolly"></i></span> Request Machine</a></li>
                    <li><a href="{{ route('requests.machine-parts') }}" class="@if(request()->routeIs('requests.machine-parts*')) active @endif"><span class="nav-icon"><i class="fas fa-tools"></i></span> Request Machine Parts</a></li>
                    <li><a href="{{ route('requests.pending') }}" class="@if(request()->routeIs('requests.pending')) active @endif"><span class="nav-icon"><i class="fas fa-clock"></i></span> Pending Approvals</a></li>

                    <li class="sidebar-section-label">Maintenance</li>
                    <li><a href="{{ route('maintenance.index') }}" class="@if(request()->routeIs('maintenance.*')) active @endif"><span class="nav-icon"><i class="fas fa-file-contract"></i></span> Contracts</a></li>
                    {{-- Batch Upload removed from the sidebar; still reachable from the
                         button on the Maintenance Contracts page. Route and controller intact. --}}

                    <li class="sidebar-section-label">Reports</li>
                    <li><a href="{{ route('reports.index') }}" class="@if(request()->routeIs('reports.*')) active @endif"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Reports</a></li>

                    @if(Auth::user()->isSA() || Auth::user()->isAdmin())
                        <li class="sidebar-section-label">Administration</li>
                        <li><a href="{{ route('users.index') }}" class="@if(request()->routeIs('users.*')) active @endif"><span class="nav-icon"><i class="fas fa-users"></i></span> Users</a></li>
                    @endif
                @endif
            </ul>
        </aside>
    @endauth

    <!-- Main Content -->
    <div class="@auth main-content @endauth">
        @yield('content')
    </div>

    <!-- jQuery (required for DataTables + Select2) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <style>
        /* DataTables overrides */
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 5px 2rem 5px 10px;
            font-size: 0.845rem;
            color: #0f172a;
            outline: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.6rem center;
            background-size: 14px 10px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 0.845rem;
            color: #0f172a;
            outline: none;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        .dataTables_wrapper .dataTables_length { margin-bottom: 12px; }
        .dataTables_wrapper .dataTables_filter { margin-bottom: 12px; }
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .dataTables_wrapper .dataTables_info {
            font-size: 0.78rem;
            color: #94a3b8;
            padding-top: 6px;
        }
        .dataTables_wrapper .dataTables_paginate {
            padding-top: 4px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            font-size: 0.78rem;
            padding: 4px 10px !important;
            margin: 0 2px;
            border: 1px solid #e2e8f0 !important;
            color: #4f46e5 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #4f46e5 !important;
            border-color: #4f46e5 !important;
            color: #fff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #ede9fe !important;
            border-color: #e2e8f0 !important;
            color: #4f46e5 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            color: #cbd5e1 !important;
            border-color: #f1f5f9 !important;
            background: transparent !important;
        }
        div.dataTables_wrapper div.dataTables_filter input { width: 220px; }
        .dataTables_empty { color: #94a3b8; font-size: 0.845rem; padding: 32px !important; }
    </style>

    <style>
        /* Select2 overrides to match app style */
        .select2-container--bootstrap-5 .select2-selection {
            font-size: 0.845rem;
            border-color: #d1d5db;
            border-radius: 8px;
            min-height: 38px;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-top: 5px;
            color: #0f172a;
        }
        .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        .select2-container--bootstrap-5.select2-container--open .select2-selection {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        .select2-container--bootstrap-5 .select2-dropdown {
            border-color: #4f46e5;
            border-radius: 8px;
            font-size: 0.845rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .select2-container--bootstrap-5 .select2-search__field {
            border-radius: 6px;
            font-size: 0.845rem;
        }
        .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: #4f46e5;
        }
        /* Smaller variant for table rows */
        .select2-sm .select2-container--bootstrap-5 .select2-selection {
            min-height: 31px;
            font-size: 0.78rem;
        }
        .select2-sm .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-top: 2px;
            font-size: 0.78rem;
        }
    </style>

    <script>
        // Global Select2 helper for dynamic row selects
        window.initRowSelect2 = function(container, modalEl) {
            $(container).find('select.form-select').not('.no-select2').each(function() {
                $(this).wrap('<span class="select2-sm"></span>');
                $(this).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: modalEl ? $(modalEl) : $('body'),
                    placeholder: $(this).find('option:first').text()
                });
            });
        };
    </script>

    <script>
        $(document).ready(function () {
            // Initialize Select2 on all static header selects
            $('select.form-select').not('.no-select2').each(function() {
                $(this).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $(this).find('option:first').text()
                });
            });

            $('table.table').not('.no-datatable').each(function () {
                if (!$.fn.DataTable.isDataTable(this)) {
                    $(this).DataTable({
                        responsive: true,
                        pageLength: 10,
                        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                        language: {
                            search: '',
                            searchPlaceholder: 'Search...',
                            lengthMenu: 'Show _MENU_ entries',
                            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                            paginate: { previous: '← Prev', next: 'Next →' }
                        },
                        pagingType: 'simple',
                        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
                        order: []
                    });
                }
            });
        });
    </script>

    @yield('scripts')
</body>
</html>
