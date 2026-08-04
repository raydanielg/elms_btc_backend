<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} — Installer</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a3a1a 0%, #2d5a2d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .install-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 640px;
            width: 100%;
            overflow: hidden;
        }
        .install-header {
            background: linear-gradient(135deg, #1a3a1a, #2d5a2d);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .install-header h1 { font-size: 1.4rem; font-weight: 700; }
        .install-header p { font-size: 0.85rem; opacity: 0.8; margin-top: 0.3rem; }
        .install-body { padding: 2rem; }
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        .steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e2e8f0;
            z-index: 0;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            z-index: 1;
            flex: 1;
        }
        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            transition: all 0.3s;
        }
        .step.active .step-circle {
            background: #1a3a1a;
            color: white;
            box-shadow: 0 0 0 4px rgba(26,58,26,0.2);
        }
        .step.done .step-circle {
            background: #16a34a;
            color: white;
        }
        .step-label { font-size: 0.7rem; color: #64748b; text-align: center; }
        .step.active .step-label { color: #1a3a1a; font-weight: 600; }
        .step.done .step-label { color: #16a34a; font-weight: 600; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.4rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.15s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #1a3a1a;
            box-shadow: 0 0 0 3px rgba(26,58,26,0.1);
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-primary { background: #1a3a1a; color: white; }
        .btn-primary:hover { background: #2d5a2d; }
        .btn-outline { border: 1px solid #1a3a1a; color: #1a3a1a; background: white; }
        .btn-outline:hover { background: #1a3a1a; color: white; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .actions { display: flex; justify-content: space-between; margin-top: 1.5rem; }
        .req-list { list-style: none; }
        .req-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            border: 1px solid #e2e8f0;
        }
        .req-item.ok { background: #f0fdf4; border-color: #bbf7d0; }
        .req-item.fail { background: #fef2f2; border-color: #fecaca; }
        .req-name { font-size: 0.85rem; font-weight: 500; }
        .req-status { font-size: 0.8rem; font-weight: 600; }
        .req-status.ok { color: #16a34a; }
        .req-status.fail { color: #dc2626; }
        .alert {
            padding: 0.875rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .output-box {
            background: #1e293b;
            color: #4ade80;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'SF Mono', Monaco, Consolas, monospace;
            font-size: 0.75rem;
            max-height: 200px;
            overflow-y: auto;
            margin: 1rem 0;
            white-space: pre-wrap;
        }
        .icon-large { font-size: 3rem; text-align: center; margin: 1rem 0; }
        .text-center { text-align: center; }
        .text-muted { color: #64748b; font-size: 0.85rem; }
        .mt-2 { margin-top: 1rem; }
        .mt-4 { margin-top: 2rem; }
    </style>
</head>
<body>
    <div class="install-card">
        <div class="install-header">
            <h1>Butimba Teachers College</h1>
            <p>ELMS Backend Installer</p>
        </div>
        <div class="install-body">
            @yield('content')
        </div>
    </div>
</body>
</html>
