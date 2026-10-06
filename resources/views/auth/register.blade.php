<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ATI Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 24px;
        }

        .auth-card {
            width: 100%;
            max-width: 460px;
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            overflow: hidden;
            background: white;
        }

        .auth-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 32px 24px;
            text-align: center;
        }

        .auth-header i.brand-icon { font-size: 44px; margin-bottom: 12px; display: block; }
        .auth-header h1 { font-size: 24px; font-weight: 700; margin: 0 0 4px; }
        .auth-header p  { font-size: 13px; margin: 0; opacity: 0.85; }

        .auth-body { padding: 32px; }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #444;
            margin-bottom: 6px;
        }

        .form-label .required { color: #e53e3e; margin-left: 2px; }

        .form-control {
            border: 1px solid #dde1e7;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }

        .form-control.auto-filled {
            background: #f8f7ff;
            border-color: #c4b5fd;
        }

        .input-hint {
            font-size: 11px;
            color: #888;
            margin-top: 4px;
        }

        .domain-badge {
            display: inline-flex;
            align-items: center;
            background: #f3f0ff;
            border: 1px solid #c4b5fd;
            border-radius: 6px;
            padding: 3px 10px;
            font-size: 12px;
            color: #7c3aed;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 8px;
        }

        .btn-submit:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102,126,234,0.4);
        }

        .auth-footer {
            text-align: center;
            padding: 16px 24px;
            background: #f9fafb;
            border-top: 1px solid #f0f0f0;
            font-size: 13px;
            color: #666;
        }

        .auth-footer a { color: #667eea; font-weight: 600; text-decoration: none; }
        .auth-footer a:hover { text-decoration: underline; }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            color: #166534;
            font-weight: 600;
        }

        .divider { margin: 20px 0; border-color: #f0f0f0; }

        .password-toggle {
            position: relative;
        }
        .password-toggle .toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 0;
            font-size: 14px;
        }
        .password-toggle input { padding-right: 40px; }

        .strength-bar {
            display: flex;
            gap: 4px;
            margin-top: 8px;
        }

        .strength-bar span {
            flex: 1;
            height: 4px;
            border-radius: 99px;
            background: #e5e7eb;
            transition: background 0.3s;
        }

        .strength-label {
            font-size: 11px;
            margin-top: 4px;
            font-weight: 600;
            color: #aaa;
            transition: color 0.3s;
        }

        .req-list {
            list-style: none;
            margin: 10px 0 0;
            padding: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px 12px;
        }

        .req-list li {
            font-size: 11.5px;
            color: #aaa;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s;
        }

        .req-list li i {
            font-size: 10px;
            width: 14px;
            text-align: center;
        }

        .req-list li.met { color: #16a34a; }
        .req-list li.met i::before { content: '\f058'; font-family: 'Font Awesome 6 Free'; font-weight: 900; }
        .req-list li:not(.met) i::before { content: '\f111'; font-family: 'Font Awesome 6 Free'; font-weight: 400; }
    </style>

    @include('partials.notify')
</head>
<body>

<div class="auth-card">
    <!-- Header -->
    <div class="auth-header">
        <i class="fas fa-boxes brand-icon"></i>
        <h1>Create Account</h1>
        <p>ATI Inventory Management System</p>
    </div>

    <!-- Body -->
    <div class="auth-body">

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show py-2 px-3 mb-3" role="alert" style="font-size:13px;">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- <div class="domain-badge mb-3">
            <i class="fas fa-shield-alt me-1"></i>
            Requires @atiph.net email address
        </div> -->

        <form method="POST" action="{{ route('register') }}" id="registerForm">
            @csrf

            {{-- Email --}}
            <div class="mb-3">
                <label class="form-label" for="email">
                    Work Email <span class="required">*</span>
                </label>
                <input
                    type="email"
                    class="form-control @error('email') is-invalid @enderror"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="yourname@atiph.net"
                    required
                    autofocus
                >
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @else
                    <div class="input-hint">Must be a valid @atiph.net address.</div>
                @enderror
            </div>

            {{-- Name (auto-filled) --}}
            <div class="mb-3">
                <label class="form-label" for="name">
                    Full Name <span class="required">*</span>
                </label>
                <input
                    type="text"
                    class="form-control auto-filled @error('name') is-invalid @enderror"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    placeholder="Auto-filled from email"
                    required
                >
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @else
                    <div class="input-hint">Auto-filled from your email — you can edit this.</div>
                @enderror
            </div>

            {{-- Username (auto-filled) --}}
            <div class="mb-3">
                <label class="form-label" for="username">
                    Username <span class="required">*</span>
                </label>
                <input
                    type="text"
                    class="form-control @error('username') is-invalid @enderror"
                    id="username"
                    name="username"
                    value="{{ old('username') }}"
                    placeholder="Choose a username"
                    required
                >
                @error('username')
                    <div class="invalid-feedback">{{ $message }}</div>
                @else
                    <!-- <div class="input-hint">This will be your login username.</div> -->
                @enderror
            </div>

            {{-- Hub --}}
            <div class="mb-3">
                <label class="form-label" for="hub_id">
                    Hub <span class="required">*</span>
                </label>
                <select
                    class="form-control @error('hub_id') is-invalid @enderror"
                    id="hub_id"
                    name="hub_id"
                    required
                >
                    <option value="">-- Select Hub --</option>
                    @foreach ($hubs as $hub)
                        <option value="{{ $hub->id }}" @selected(old('hub_id') == $hub->id)>{{ $hub->hub_name }}</option>
                    @endforeach
                </select>
                @error('hub_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @else
                    <div class="input-hint">The branch you are based at.</div>
                @enderror
            </div>

            <!-- <hr class="divider"> -->

            {{-- Password --}}
            <div class="mb-3">
                <label class="form-label" for="password">
                    Password <span class="required">*</span>
                </label>
                <div class="password-toggle">
                    <input
                        type="password"
                        class="form-control @error('password') is-invalid @enderror"
                        id="password"
                        name="password"
                        placeholder="Create a strong password"
                        required
                        oninput="checkStrength(this.value)"
                    >
                    <button type="button" class="toggle-btn" onclick="togglePassword('password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="strength-bar" id="strengthBar">
                    <span id="s1"></span><span id="s2"></span><span id="s3"></span><span id="s4"></span>
                </div>
                <div class="strength-label" id="strengthLabel">Enter a password</div>
                <ul class="req-list" id="reqList">
                    <li id="req-len"><i></i> At least 8 characters</li>
                    <li id="req-upper"><i></i> Uppercase letter</li>
                    <li id="req-lower"><i></i> Lowercase letter</li>
                    <li id="req-num"><i></i> Number</li>
                    <li id="req-special"><i></i> Special character</li>
                </ul>
            </div>

            {{-- Confirm Password --}}
            <div class="mb-3">
                <label class="form-label" for="password_confirmation">
                    Confirm Password <span class="required">*</span>
                </label>
                <div class="password-toggle">
                    <input
                        type="password"
                        class="form-control"
                        id="password_confirmation"
                        name="password_confirmation"
                        placeholder="Re-enter password"
                        required
                    >
                    <button type="button" class="toggle-btn" onclick="togglePassword('password_confirmation', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            {{-- Role info --}}
            <div class="mb-4">
                <span class="role-badge">
                    <i class="fas fa-hard-hat"></i> You will be registered as: Engineer
                </span>
            </div>

            <button type="submit" class="btn btn-submit">
                <i class="fas fa-user-plus me-2"></i> Create Account
            </button>
        </form>
    </div>

    <!-- Footer -->
    <div class="auth-footer">
        Already have an account? <a href="{{ route('login') }}">Log In</a>
        &nbsp;|&nbsp;
        <a href="{{ url('/') }}"><i class="fas fa-home"></i> Home</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-fill name and username from email
    document.getElementById('email').addEventListener('input', function () {
        const email = this.value.trim();
        const atIndex = email.indexOf('@');

        if (atIndex === -1) return;

        const domain = email.slice(atIndex + 1).toLowerCase();
        const localPart = email.slice(0, atIndex);

        // Only auto-fill for @atiph.net
        if (domain !== 'atiph.net') return;

        // Name: split by dot/underscore/dash, capitalize each word
        const nameField = document.getElementById('name');
        if (!nameField.dataset.manuallyEdited) {
            const words = localPart.split(/[._-]+/).filter(Boolean);
            const formatted = words.map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
            nameField.value = formatted;
        }
    });

    // Track manual edits to name so auto-fill doesn't overwrite user's changes
    document.getElementById('name').addEventListener('keydown', function () {
        this.dataset.manuallyEdited = 'true';
    });

    // Password strength checker
    function checkStrength(val) {
        const rules = {
            'req-len':     val.length >= 8,
            'req-upper':   /[A-Z]/.test(val),
            'req-lower':   /[a-z]/.test(val),
            'req-num':     /[0-9]/.test(val),
            'req-special': /[@$!%*#?&^_\-]/.test(val),
        };

        let score = Object.values(rules).filter(Boolean).length;

        Object.entries(rules).forEach(([id, met]) => {
            document.getElementById(id).classList.toggle('met', met);
        });

        const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
        const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

        for (let i = 1; i <= 4; i++) {
            const seg = document.getElementById('s' + i);
            seg.style.background = i <= score ? colors[score] : '#e5e7eb';
        }

        const lbl = document.getElementById('strengthLabel');
        lbl.textContent = val.length === 0 ? 'Enter a password' : labels[score] || 'Weak';
        lbl.style.color = val.length === 0 ? '#aaa' : colors[score] || '#ef4444';
    }

    // Password visibility toggle
    function togglePassword(fieldId, btn) {
        const field = document.getElementById(fieldId);
        const icon = btn.querySelector('i');
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // Client-side validation before submit
    document.getElementById('registerForm').addEventListener('submit', function (e) {
        const email = document.getElementById('email').value.trim().toLowerCase();
        if (!email.endsWith('@atiph.net')) {
            e.preventDefault();
            notify('Please use a valid @atiph.net email address.', 'warning');
            document.getElementById('email').focus();
            return;
        }

        const pwd = document.getElementById('password').value;
        const allMet = pwd.length >= 8
            && /[A-Z]/.test(pwd)
            && /[a-z]/.test(pwd)
            && /[0-9]/.test(pwd)
            && /[@$!%*#?&^_\-]/.test(pwd);

        if (!allMet) {
            e.preventDefault();
            notify('Password does not meet the requirements.', 'warning');
            document.getElementById('password').focus();
        }
    });
</script>
</body>
</html>
