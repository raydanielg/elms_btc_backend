<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} — API Documentation</title>
    <style>
        :root {
            --primary: #1a3a1a;
            --primary-light: #2d5a2d;
            --accent: #6b8e6b;
            --bg: #f8faf9;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --code-bg: #f1f5f9;
            --get: #3b82f6;
            --post: #10b981;
            --put: #f59e0b;
            --patch: #8b5cf6;
            --delete: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }
        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 2rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .header h1 { font-size: 1.5rem; font-weight: 700; }
        .header .subtitle { font-size: 0.875rem; opacity: 0.8; margin-top: 0.25rem; }
        .header .badge {
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 2rem;
        }
        .sidebar {
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
            align-self: start;
        }
        .sidebar h3 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            padding-left: 0.75rem;
        }
        .sidebar a {
            display: block;
            padding: 0.4rem 0.75rem;
            color: var(--text);
            text-decoration: none;
            font-size: 0.875rem;
            border-radius: 6px;
            transition: all 0.15s;
        }
        .sidebar a:hover {
            background: var(--accent);
            color: white;
        }
        .sidebar .count {
            float: right;
            font-size: 0.7rem;
            color: var(--text-muted);
            background: var(--code-bg);
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
        }
        .content { min-width: 0; }
        .group {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .group-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 1rem 1.5rem;
            font-size: 1.125rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .group-header .count {
            background: rgba(255,255,255,0.2);
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
        }
        .endpoint {
            border-bottom: 1px solid var(--border);
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background 0.15s;
        }
        .endpoint:hover { background: var(--bg); }
        .endpoint:last-child { border-bottom: none; }
        .method {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            min-width: 50px;
            text-align: center;
            white-space: nowrap;
        }
        .method-GET { background: rgba(59,130,246,0.1); color: var(--get); }
        .method-POST { background: rgba(16,185,129,0.1); color: var(--post); }
        .method-PUT { background: rgba(245,158,11,0.1); color: var(--put); }
        .method-PATCH { background: rgba(139,92,246,0.1); color: var(--patch); }
        .method-DELETE { background: rgba(239,68,68,0.1); color: var(--delete); }
        .uri {
            font-family: 'SF Mono', Monaco, Consolas, monospace;
            font-size: 0.85rem;
            color: var(--text);
            word-break: break-all;
        }
        .auth-tag {
            margin-left: auto;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            white-space: nowrap;
        }
        .auth-public { background: rgba(100,116,139,0.1); color: var(--text-muted); }
        .auth-auth { background: rgba(245,158,11,0.1); color: var(--put); }
        .auth-role { background: rgba(139,92,246,0.1); color: var(--patch); }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
        }
        .stat-card .num { font-size: 1.75rem; font-weight: 700; color: var(--primary); }
        .stat-card .label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .footer {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
            font-size: 0.8rem;
            border-top: 1px solid var(--border);
            margin-top: 2rem;
        }
        @media (max-width: 768px) {
            .container { grid-template-columns: 1fr; }
            .sidebar { position: static; max-height: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>{{ $appName }} — API Documentation</h1>
                <div class="subtitle">Butimba Teachers College ELMS Backend</div>
            </div>
            <span class="badge">Laravel {{ app()->version() }}</span>
        </div>
    </div>

    <div class="container">
        <nav class="sidebar">
            <h3>Sections</h3>
            @php $total = 0; @endphp
            @foreach ($endpoints as $group => $routes)
                @php $total += count($routes); @endphp
                <a href="#{{ \Str::slug($group) }}">{{ $group }} <span class="count">{{ count($routes) }}</span></a>
            @endforeach
        </nav>

        <div class="content">
            <div class="stats">
                <div class="stat-card">
                    <div class="num">{{ $total }}</div>
                    <div class="label">Total Endpoints</div>
                </div>
                <div class="stat-card">
                    <div class="num">{{ count($endpoints) }}</div>
                    <div class="label">Categories</div>
                </div>
                <div class="stat-card">
                    <div class="num">REST</div>
                    <div class="label">Architecture</div>
                </div>
                <div class="stat-card">
                    <div class="num">Sanctum</div>
                    <div class="label">Auth</div>
                </div>
            </div>

            @foreach ($endpoints as $group => $routes)
                <div class="group" id="{{ \Str::slug($group) }}">
                    <div class="group-header">
                        {{ $group }}
                        <span class="count">{{ count($routes) }} endpoints</span>
                    </div>
                    @foreach ($routes as $route)
                        @php
                            $methods = explode('|', $route['method']);
                            $firstMethod = $methods[0];
                            $hasAuth = in_array('auth:sanctum', $route['middleware']);
                            $hasRole = collect($route['middleware'])->contains(fn($m) => str_starts_with($m, 'role:'));
                            $roleLabel = '';
                            if ($hasRole) {
                                foreach ($route['middleware'] as $m) {
                                    if (str_starts_with($m, 'role:')) {
                                        $roleLabel = str_replace('role:', '', $m);
                                        break;
                                    }
                                }
                            }
                        @endphp
                        <div class="endpoint">
                            <span class="method method-{{ $firstMethod }}">{{ $firstMethod }}</span>
                            <span class="uri">{{ $route['uri'] }}</span>
                            @if ($hasRole)
                                <span class="auth-tag auth-role">{{ $roleLabel }}</span>
                            @elseif ($hasAuth)
                                <span class="auth-tag auth-auth">Auth</span>
                            @else
                                <span class="auth-tag auth-public">Public</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach

            <div class="footer">
                Generated {{ now()->format('Y-m-d H:i:s') }} &middot; {{ $appName }} ELMS API
            </div>
        </div>
    </div>
</body>
</html>
