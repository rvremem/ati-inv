<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATI Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
        }

        /* ── LEFT PANEL ── */
        .panel-left {
            flex: 2;
            background: linear-gradient(160deg, #0f0c29 0%, #302b63 55%, #24243e 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 56px;
            position: relative;
            overflow: hidden;
        }

        /* subtle decorative circle */
        .panel-left::before {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(102,126,234,0.15) 0%, transparent 70%);
            top: -100px; right: -100px;
            pointer-events: none;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 48px;
        }

        .brand-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: white;
            box-shadow: 0 8px 24px rgba(102,126,234,0.4);
        }

        .brand-name {
            font-size: 20px;
            font-weight: 700;
            color: white;
            line-height: 1.1;
        }

        .brand-name small {
            display: block;
            font-size: 11px;
            font-weight: 400;
            color: rgba(255,255,255,0.45);
            letter-spacing: 0.5px;
        }

        .left-heading {
            font-size: 38px;
            font-weight: 800;
            color: white;
            line-height: 1.15;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }

        .left-heading span { color: #a78bfa; }

        .left-desc {
            font-size: 15px;
            color: rgba(255,255,255,0.6);
            line-height: 1.75;
            margin-bottom: 20px;
            max-width: 420px;
        }

        .branches-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 36px;
        }

        .branch-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 50px;
            padding: 4px 12px;
            font-size: 12px;
            color: rgba(255,255,255,0.65);
            font-weight: 500;
        }

        .branch-pill i { font-size: 10px; color: #a78bfa; }

        .overview-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 48px;
        }

        .overview-list li {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            color: rgba(255,255,255,0.75);
            font-size: 14px;
            line-height: 1.5;
        }

        .overview-list li .icon-wrap {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: rgba(167,139,250,0.15);
            border: 1px solid rgba(167,139,250,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; color: #a78bfa;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .overview-list li strong {
            display: block;
            color: white;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 1px;
        }

        .left-footer {
            font-size: 12px;
            color: rgba(255,255,255,0.25);
            margin-top: auto;
        }

        /* ── RIGHT PANEL ── */
        .panel-right {
            flex: 1;
            flex-shrink: 0;
            background: #f9fafb;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 48px 40px;
            border-left: 1px solid #e8eaed;
            overflow-y: auto;
        }

        .right-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 6px;
        }

        .right-subtitle {
            font-size: 13px;
            color: #888;
            margin-bottom: 28px;
        }

        /* Login form */
        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }

        .form-control {
            border: 1px solid #dde1e7;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            background: white;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.12);
        }

        .btn-login {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.2s;
            margin-top: 4px;
        }

        .btn-login:hover {
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(102,126,234,0.4);
        }

        .divider-text {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
            color: #bbb;
            font-size: 12px;
        }

        .divider-text::before,
        .divider-text::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        /* Register card */
        .register-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: all 0.2s;
        }

        .register-card:hover { border-color: #a78bfa; box-shadow: 0 4px 16px rgba(167,139,250,0.12); }

        .register-card-text strong {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 2px;
        }

        .register-card-text span {
            font-size: 12px;
            color: #888;
        }

        .btn-register {
            padding: 9px 18px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .btn-register:hover {
            color: white;
            box-shadow: 0 4px 14px rgba(102,126,234,0.4);
        }

        .alert { font-size: 13px; border-radius: 8px; border: none; padding: 10px 14px; }

        /* ── Mobile ── */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .panel-left { padding: 40px 28px; }
            .left-heading { font-size: 28px; }
            .panel-right { width: 100%; padding: 36px 28px; }
        }
    </style>
</head>
<body>

    <!-- LEFT: Overview -->
    <div class="panel-left">
        <!-- Brand -->
        <div class="brand">
            <div class="brand-icon"><i class="fas fa-boxes"></i></div>
            <div class="brand-name">
                ATI Inventory
                <small>Management System</small>
            </div>
        </div>

        <!-- Heading -->
        <h1 class="left-heading">
            One system for all<br>
            <span>inventory operations.</span>
        </h1>

        <p class="left-desc">
            A centralized platform for managing parts, machines, requests, and maintenance contracts
            across all ATI branch locations — accessible to authorized personnel only.
        </p>

        <!-- Branch pills -->
        <div class="branches-strip">
            <span class="branch-pill"><i class="fas fa-location-dot"></i> Head Office</span>
            <span class="branch-pill"><i class="fas fa-location-dot"></i> Cebu</span>
            <span class="branch-pill"><i class="fas fa-location-dot"></i> Davao</span>
            <span class="branch-pill"><i class="fas fa-location-dot"></i> Iloilo</span>
            <span class="branch-pill"><i class="fas fa-location-dot"></i> Cagayan de Oro</span>
            <span class="branch-pill"><i class="fas fa-location-dot"></i> Bacolod</span>
            <span class="branch-pill"><i class="fas fa-location-dot"></i> Baguio</span>
        </div>

        <!-- Overview list -->
        <ul class="overview-list">
            <li>
                <div class="icon-wrap"><i class="fas fa-boxes"></i></div>
                <div>
                    <strong>Inventory & Stock</strong>
                    Track parts and equipment across locations with serial number support and minimum stock alerts.
                </div>
            </li>
            <li>
                <div class="icon-wrap"><i class="fas fa-truck-loading"></i></div>
                <div>
                    <strong>Receiving & Returns</strong>
                    Log incoming items from suppliers, record purchase orders, and process returns.
                </div>
            </li>
            <li>
                <div class="icon-wrap"><i class="fas fa-clipboard-check"></i></div>
                <div>
                    <strong>Requests & Approvals</strong>
                    Engineers request parts or machines; admins approve and track issuance with MRIS numbers.
                </div>
            </li>
            <li>
                <div class="icon-wrap"><i class="fas fa-file-contract"></i></div>
                <div>
                    <strong>Maintenance Contracts</strong>
                    Manage service contracts, coverage dates, and PM reports for all customer sites.
                </div>
            </li>
            <li>
                <div class="icon-wrap"><i class="fas fa-chart-bar"></i></div>
                <div>
                    <strong>Reports & Audit Logs</strong>
                    Generate receiving, outbound, stock, defective, and activity reports at any time.
                </div>
            </li>
        </ul>

        <div class="left-footer">
            &copy; {{ date('Y') }} Automated Technologies Inc. &nbsp;·&nbsp; For internal use only.
        </div>
    </div>

    <!-- RIGHT: Login & Register -->
    <div class="panel-right">

        <h2 class="right-title">Welcome back</h2>
        <p class="right-subtitle">Sign in to your ATI account</p>

        <!-- Alerts -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Login Form -->
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input
                    type="email"
                    class="form-control @error('email') is-invalid @enderror"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="Enter your email"
                    required
                    autofocus
                    autocomplete="username"
                >
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input
                    type="password"
                    class="form-control @error('password') is-invalid @enderror"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="text-end mt-2">
                    <a href="{{ route('password.request') }}"
                       style="font-size:12.5px; color:#667eea; font-weight:600; text-decoration:none;">
                        Forgot password?
                    </a>
                </div>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Sign In
            </button>
        </form>

        <!-- Divider -->
        <div class="divider-text">or</div>

        <!-- Register Card -->
        <div class="register-card">
            <div class="register-card-text">
                <strong>New to ATI Inventory?</strong>
                <span>Use your @atiph.net email to register.</span>
            </div>
            <a href="{{ route('register') }}" class="btn-register">
                <i class="fas fa-user-plus me-1"></i> Register
            </a>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
