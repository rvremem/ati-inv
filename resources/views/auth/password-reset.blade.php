<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ATI Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 24px;
        }
        .auth-card {
            width: 100%; max-width: 460px; border: none; border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4); overflow: hidden; background: white;
        }
        .auth-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 32px 24px; text-align: center;
        }
        .auth-header i.brand-icon { font-size: 44px; margin-bottom: 12px; display: block; }
        .auth-header h1 { font-size: 24px; font-weight: 700; margin: 0 0 4px; }
        .auth-header p  { font-size: 13px; margin: 0; opacity: 0.85; }
        .auth-body { padding: 32px; }
        .form-label { font-size: 13px; font-weight: 600; color: #444; margin-bottom: 6px; }
        .form-label .required { color: #e53e3e; margin-left: 2px; }
        .form-control {
            border: 1px solid #dde1e7; border-radius: 8px; padding: 10px 14px;
            font-size: 14px; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.15); }
        .input-hint { font-size: 11px; color: #888; margin-top: 4px; }
        .btn-submit {
            background: linear-gradient(135deg, #667eea, #764ba2); border: none; color: white;
            padding: 12px; border-radius: 8px; font-weight: 700; font-size: 15px; width: 100%;
            transition: transform 0.2s, box-shadow 0.2s; margin-top: 8px;
        }
        .btn-submit:hover { color: white; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102,126,234,0.4); }
        .auth-footer {
            text-align: center; padding: 16px 24px; background: #f9fafb;
            border-top: 1px solid #f0f0f0; font-size: 13px; color: #666;
        }
        .auth-footer a { color: #667eea; font-weight: 600; text-decoration: none; }
        .auth-footer a:hover { text-decoration: underline; }
        .password-toggle { position: relative; }
        .password-toggle .toggle-btn {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #999; cursor: pointer; padding: 0; font-size: 14px;
        }
        .password-toggle input { padding-right: 40px; }
        .req-list {
            list-style: none; margin: 10px 0 0; padding: 0;
            display: grid; grid-template-columns: 1fr 1fr; gap: 5px 12px;
        }
        .req-list li {
            font-size: 11.5px; color: #aaa; display: flex; align-items: center;
            gap: 5px; transition: color 0.2s;
        }
        .req-list li i { font-size: 10px; width: 14px; text-align: center; }
        .req-list li.met { color: #16a34a; }
        .req-list li.met i::before { content: '\f058'; font-family: 'Font Awesome 6 Free'; font-weight: 900; }
        .req-list li:not(.met) i::before { content: '\f111'; font-family: 'Font Awesome 6 Free'; font-weight: 400; }
        .notice {
            background: #f3f0ff; border: 1px solid #c4b5fd; border-radius: 8px;
            padding: 10px 14px; font-size: 12.5px; color: #5b21b6; margin-bottom: 18px;
        }
    </style>

    @include('partials.notify')
</head>
<body>

<div class="auth-card">
    <div class="auth-header">
        <i class="fas fa-key brand-icon"></i>
        <h1>Reset Password</h1>
        <p>ATI Inventory Management System</p>
    </div>

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

        <div class="notice">
            <i class="fas fa-shield-alt me-1"></i>
            Your password will not change until you confirm it from the link we email you.
            Your current password keeps working until then.
        </div>

        <form method="POST" action="{{ route('password.send') }}" id="resetForm">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="username">Username <span class="required">*</span></label>
                <input
                    type="text"
                    class="form-control @error('username') is-invalid @enderror"
                    id="username"
                    name="username"
                    value="{{ old('username') }}"
                    placeholder="Enter your username"
                    required
                    autofocus
                >
                @error('username')
                    <div class="invalid-feedback">{{ $message }}</div>
                @else
                    <div class="input-hint">The confirmation is sent to the email on that account.</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">New Password <span class="required">*</span></label>
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
                <ul class="req-list" id="reqList">
                    <li id="req-len"><i></i> At least 8 characters</li>
                    <li id="req-upper"><i></i> Uppercase letter</li>
                    <li id="req-lower"><i></i> Lowercase letter</li>
                    <li id="req-num"><i></i> Number</li>
                    <li id="req-special"><i></i> Special character</li>
                </ul>
            </div>

            <div class="mb-4">
                <label class="form-label" for="password_confirmation">Confirm New Password <span class="required">*</span></label>
                <div class="password-toggle">
                    <input
                        type="password"
                        class="form-control"
                        id="password_confirmation"
                        name="password_confirmation"
                        placeholder="Re-enter new password"
                        required
                    >
                    <button type="button" class="toggle-btn" onclick="togglePassword('password_confirmation', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-submit">
                <i class="fas fa-paper-plane me-2"></i> Send Confirmation Email
            </button>
        </form>
    </div>

    <div class="auth-footer">
        Remembered it? <a href="{{ route('login') }}">Log In</a>
        &nbsp;|&nbsp;
        <a href="{{ url('/') }}"><i class="fas fa-home"></i> Home</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function checkStrength(val) {
        const rules = {
            'req-len':     val.length >= 8,
            'req-upper':   /[A-Z]/.test(val),
            'req-lower':   /[a-z]/.test(val),
            'req-num':     /[0-9]/.test(val),
            'req-special': /[@$!%*#?&^_\-]/.test(val),
        };
        Object.entries(rules).forEach(([id, met]) => {
            document.getElementById(id).classList.toggle('met', met);
        });
    }

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

    document.getElementById('resetForm').addEventListener('submit', function (e) {
        const pwd = document.getElementById('password').value;
        const ok = pwd.length >= 8 && /[A-Z]/.test(pwd) && /[a-z]/.test(pwd)
                && /[0-9]/.test(pwd) && /[@$!%*#?&^_\-]/.test(pwd);
        if (!ok) {
            e.preventDefault();
            notify('Password does not meet the requirements.', 'warning');
            document.getElementById('password').focus();
            return;
        }
        if (pwd !== document.getElementById('password_confirmation').value) {
            e.preventDefault();
            notify('Passwords do not match.', 'warning');
            document.getElementById('password_confirmation').focus();
        }
    });
</script>
</body>
</html>
