<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} — API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.18.2/swagger-ui.css" />
    <style>
        body { margin: 0; }
        .topbar { display: none; }
        .swagger-ui .topbar { background-color: #1a3a1a; }
        .swagger-ui .info .title { color: #1a3a1a; }
        .header-bar {
            background: linear-gradient(135deg, #1a3a1a, #2d5a2d);
            color: white;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .header-bar h1 { font-size: 1.1rem; font-weight: 700; margin: 0; }
        .header-bar .badge {
            background: rgba(255,255,255,0.2);
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <h1>Butimba Teachers College — ELMS API</h1>
        <span class="badge">Swagger UI</span>
    </div>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.18.2/swagger-ui-bundle.js" crossorigin></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '{{ $specUrl }}',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [SwaggerUIBundle.presets.apis],
                layout: 'BaseLayout',
                filter: true,
                showExtensions: true,
                showCommonExtensions: true,
                tryItOutEnabled: true,
                requestInterceptor: (req) => {
                    req.headers['Accept'] = 'application/json';
                    return req;
                },
            });
        };
    </script>
</body>
</html>
