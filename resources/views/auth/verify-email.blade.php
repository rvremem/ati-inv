<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - ATI Inventory</title>
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
        .auth-body { padding: 32px; text-align: center; }
        .sent-to {
            display: inline-block;
            background: #f3f0ff;
            border: 1px solid #c4b5fd;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 14px;
            color: #5b21b6;
            font-weight: 600;
            margin: 4px 0 18px;
            word-break: break-all;
        }
        .lead-text { font-size: 14px; color: #555; line-height: 1.6; margin-bottom: 4px; }
        .steps {
            text-align: left;
            font-size: 13px;
            color: #666;
            background: #f9fafb;
            border: 1px solid #eef0f3;
            border-radius: 10px;
            padding: 14px 16px 14px 32px;
            margin: 18px 0 8px;
        }
        .steps li { margin-bottom: 6px; }
        .steps li:last-child { margin-bottom: 0; }
        .btn-submit {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 11px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }
        .btn-submit:hover { color: white; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102,126,234,0.4); }
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
        .expiry-note { font-size: 11.5px; color: #999; margin-top: 14px; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="auth-header">
        <i class="fas fa-envelope-open-text brand-icon"></i>
        <h1>Check Your Email</h1>
        <p>ATI Inventory Management System</p>
    </div>

    <div class="auth-body">
        @if (session('success'))
            <div class="alert alert-success py-2 px-3 mb-3" style="font-size:13px;">
                {{ session('success') }}
            </div>
        @endif

        <p class="lead-text">We sent a verification link to</p>
        <div class="sent-to">{{ $email }}</div>

        <ol class="steps">
            <li>Open that inbox and find the email from ATI Inventory.</li>
            <li>Click <strong>Verify Email Address</strong> in the message.</li>
            <li>You'll be signed in automatically and taken to your dashboard.</li>
        </ol>

        <p class="expiry-note">
            <i class="fas fa-clock me-1"></i>
            The link expires in 60 minutes. Not in your inbox? Check the spam folder.
        </p>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-submit">
                <i class="fas fa-paper-plane me-2"></i> Resend Verification Email
            </button>
        </form>
    </div>

    <div class="auth-footer">
        Already verified? <a href="{{ route('login') }}">Log In</a>
        &nbsp;|&nbsp;
        <a href="{{ url('/') }}"><i class="fas fa-home"></i> Home</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
