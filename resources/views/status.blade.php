<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} — Status</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f4f0;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            max-width: 560px;
            width: 90%;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #1a3a1a, #2d5a2d);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .card-header h1 { font-size: 1.4rem; font-weight: 700; }
        .card-header p { font-size: 0.85rem; opacity: 0.8; margin-top: 0.3rem; }
        .card-body { padding: 2rem; }
        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1rem;
            border-radius: 10px;
            margin-bottom: 0.75rem;
            border: 1px solid #e2e8f0;
        }
        .status-row.ok { background: #f0fdf4; border-color: #bbf7d0; }
        .status-row.fail { background: #fef2f2; border-color: #fecaca; }
        .status-label {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .status-dot.ok { background: #16a34a; box-shadow: 0 0 8px rgba(22,163,74,0.4); }
        .status-dot.fail { background: #dc2626; box-shadow: 0 0 8px rgba(220,38,38,0.4); }
        .status-value { font-size: 0.8rem; color: #64748b; }
        .status-value.ok { color: #16a34a; font-weight: 600; }
        .status-value.fail { color: #dc2626; font-weight: 600; }
        .footer-links {
            margin-top: 1.5rem;
            display: flex;
            gap: 0.75rem;
            justify-content: center;
        }
        .footer-links a {
            display: inline-block;
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.15s;
        }
        .btn-primary {
            background: #1a3a1a;
            color: white;
        }
        .btn-primary:hover { background: #2d5a2d; }
        .btn-outline {
            border: 1px solid #1a3a1a;
            color: #1a3a1a;
        }
        .btn-outline:hover { background: #1a3a1a; color: white; }
        .version-tag {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h1>Butimba Teachers College</h1>
            <p>ELMS Backend API — System Status</p>
        </div>
        <div class="card-body">
            <div class="status-row {{ $dbOk ? 'ok' : 'fail' }}">
                <div class="status-label">
                    <span class="status-dot {{ $dbOk ? 'ok pulse' : 'fail' }}"></span>
                    Database
                </div>
                <span class="status-value {{ $dbOk ? 'ok' : 'fail' }}">{{ $dbOk ? 'Connected' : 'Failed' }}</span>
            </div>

            <div class="status-row ok">
                <div class="status-label">
                    <span class="status-dot ok pulse"></span>
                    API Server
                </div>
                <span class="status-value ok">Running</span>
            </div>

            <div class="status-row ok">
                <div class="status-label">
                    <span class="status-dot ok"></span>
                    Laravel Version
                </div>
                <span class="status-value">{{ $laravelVersion }}</span>
            </div>

            <div class="status-row ok">
                <div class="status-label">
                    <span class="status-dot ok"></span>
                    PHP Version
                </div>
                <span class="status-value">{{ $phpVersion }}</span>
            </div>

            <div class="status-row ok">
                <div class="status-label">
                    <span class="status-dot ok"></span>
                    Environment
                </div>
                <span class="status-value">{{ $env }}</span>
            </div>

            <div class="status-row {{ $dbOk ? 'ok' : 'fail' }}">
                <div class="status-label">
                    <span class="status-dot {{ $dbOk ? 'ok' : 'fail' }}"></span>
                    Overall Status
                </div>
                <span class="status-value {{ $dbOk ? 'ok' : 'fail' }}">{{ $dbOk ? 'All systems operational' : 'Issues detected' }}</span>
            </div>

            <div class="footer-links">
                <a href="/docs" class="btn-primary">View API Docs (Swagger)</a>
                <a href="/up" class="btn-outline">Health Check</a>
            </div>

            <div class="version-tag">
                {{ $appName }} &middot; {{ now()->format('Y-m-d H:i:s') }}
            </div>
        </div>
    </div>
</body>
</html>
