<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATI Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: #f4f5f7;
        }

        .card-login {
            width: 100%;
            max-width: 380px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 36px 32px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.05);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
        }

        .brand-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1.15;
        }

        .brand-name small {
            display: block;
            font-size: 11px;
            font-weight: 400;
            color: #999;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .subtitle {
            font-size: 13px;
            color: #888;
            margin-bottom: 22px;
        }

        .footer {
            text-align: center;
            font-size: 11.5px;
            color: #aaa;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
        }

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
        }

        .btn-login:hover { color: white; opacity: 0.92; }

        .link {
            font-size: 12.5px;
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
        }

        .register-line {
            text-align: center;
            font-size: 13px;
            color: #888;
            margin-top: 20px;
        }

        .alert { font-size: 13px; border-radius: 8px; border: none; padding: 10px 14px; }
    </style>
</head>
<body>

    <div class="card-login">
        <div class="brand">
            <div class="brand-icon"><i class="fas fa-boxes"></i></div>
            <div class="brand-name">
                ATI Inventory
                <small>Automated Technologies Inc.</small>
            </div>
        </div>

        <h1 class="title">Welcome back</h1>
        <p class="subtitle">Sign in with your email and password to continue.</p>

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
                    required
                    autocomplete="current-password"
                >
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="text-end mt-2">
                    <a href="{{ route('password.request') }}" class="link">Forgot password?</a>
                </div>
            </div>

            <button type="submit" class="btn btn-login">Sign In</button>
        </form>

        <div class="register-line">
            No account? <a href="{{ route('register') }}" class="link">Register</a>
        </div>

        <div class="footer">&copy; {{ date('Y') }} Automated Technologies Inc.</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
