<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class InstallerController extends Controller
{
    public function index()
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        return view('installer.welcome', [
            'appName' => 'Butimba ELMS',
        ]);
    }

    public function requirements()
    {
        $requirements = [
            'PHP >= 8.2' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'PDO Extension' => extension_loaded('pdo'),
            'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
            'mbstring Extension' => extension_loaded('mbstring'),
            'Tokenizer Extension' => extension_loaded('tokenizer'),
            'XML Extension' => extension_loaded('xml'),
            'CType Extension' => extension_loaded('ctype'),
            'JSON Extension' => extension_loaded('json'),
            'cURL Extension' => extension_loaded('curl'),
            'Fileinfo Extension' => extension_loaded('fileinfo'),
            'OpenSSL Extension' => extension_loaded('openssl'),
            'GD Extension (optional)' => extension_loaded('gd'),
        ];

        $folders = [
            'storage/app' => is_writable(base_path('storage/app')),
            'storage/framework/cache' => is_writable(base_path('storage/framework/cache')),
            'storage/framework/sessions' => is_writable(base_path('storage/framework/sessions')),
            'storage/framework/views' => is_writable(base_path('storage/framework/views')),
            'storage/logs' => is_writable(base_path('storage/logs')),
            'bootstrap/cache' => is_writable(base_path('bootstrap/cache')),
            '.env file' => is_writable(base_path('.env')) || !File::exists(base_path('.env')),
        ];

        $allPassed = collect($requirements)->every(fn ($v) => $v === true) &&
                     collect($folders)->every(fn ($v) => $v === true);

        return view('installer.requirements', [
            'appName' => 'Butimba ELMS',
            'requirements' => $requirements,
            'folders' => $folders,
            'allPassed' => $allPassed,
        ]);
    }

    public function databaseForm()
    {
        return view('installer.database', [
            'appName' => 'Butimba ELMS',
            'defaults' => [
                'DB_HOST' => env('DB_HOST', '127.0.0.1'),
                'DB_PORT' => env('DB_PORT', '3306'),
                'DB_DATABASE' => env('DB_DATABASE', ''),
                'DB_USERNAME' => env('DB_USERNAME', ''),
                'DB_PASSWORD' => env('DB_PASSWORD', ''),
                'APP_NAME' => env('APP_NAME', 'Butimba ELMS'),
                'APP_URL' => env('APP_URL', request()->getSchemeAndHttpHost()),
            ],
        ]);
    }

    public function runInstall(Request $request)
    {
        $validated = $request->validate([
            'APP_NAME' => 'required|string',
            'APP_URL' => 'required|url',
            'DB_HOST' => 'required|string',
            'DB_PORT' => 'required|string',
            'DB_DATABASE' => 'required|string',
            'DB_USERNAME' => 'required|string',
            'DB_PASSWORD' => 'nullable|string',
        ]);

        // Test database connection
        try {
            $dsn = "mysql:host={$validated['DB_HOST']};port={$validated['DB_PORT']};dbname={$validated['DB_DATABASE']};charset=utf8mb4";
            new \PDO($dsn, $validated['DB_USERNAME'], $validated['DB_PASSWORD'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (\PDOException $e) {
            return back()->withInput()->with('error', 'Database connection failed: ' . $e->getMessage());
        }

        // Write .env file
        $this->writeEnvFile($validated);

        // Clear config cache and run migrations
        try {
            Artisan::call('config:clear');
            Artisan::call('migrate', ['--force' => true]);
            $migrateOutput = Artisan::output();

            // Run seeders
            Artisan::call('db:seed', ['--force' => true]);
            $seedOutput = Artisan::output();

            // Create the installed flag
            File::put(base_path('storage/app/installed'), json_encode([
                'installed_at' => now()->toDateTimeString(),
                'version' => '1.0.0',
            ]));

            // Generate key if not set
            if (empty(env('APP_KEY'))) {
                Artisan::call('key:generate', ['--force' => true]);
            }

            return view('installer.complete', [
                'appName' => 'Butimba ELMS',
                'migrateOutput' => $migrateOutput,
                'seedOutput' => $seedOutput,
            ]);
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Installation failed: ' . $e->getMessage());
        }
    }

    public function isInstalled(): bool
    {
        return File::exists(base_path('storage/app/installed'));
    }

    private function writeEnvFile(array $config): void
    {
        $envContent = "APP_NAME=\"{$config['APP_NAME']}\"\n";
        $envContent .= "APP_ENV=production\n";
        $envContent .= "APP_KEY=\n";
        $envContent .= "APP_DEBUG=false\n";
        $envContent .= "APP_URL={$config['APP_URL']}\n\n";
        $envContent .= "APP_LOCALE=en\n";
        $envContent .= "APP_FALLBACK_LOCALE=en\n";
        $envContent .= "APP_FAKER_LOCALE=en_US\n\n";
        $envContent .= "APP_MAINTENANCE_DRIVER=file\n\n";
        $envContent .= "BCRYPT_ROUNDS=12\n\n";
        $envContent .= "LOG_CHANNEL=stack\n";
        $envContent .= "LOG_STACK=single\n";
        $envContent .= "LOG_DEPRECATIONS_CHANNEL=null\n";
        $envContent .= "LOG_LEVEL=error\n\n";
        $envContent .= "DB_CONNECTION=mysql\n";
        $envContent .= "DB_HOST={$config['DB_HOST']}\n";
        $envContent .= "DB_PORT={$config['DB_PORT']}\n";
        $envContent .= "DB_DATABASE={$config['DB_DATABASE']}\n";
        $envContent .= "DB_USERNAME={$config['DB_USERNAME']}\n";
        $envContent .= "DB_PASSWORD={$config['DB_PASSWORD']}\n\n";
        $envContent .= "SESSION_DRIVER=database\n";
        $envContent .= "SESSION_LIFETIME=120\n";
        $envContent .= "SESSION_ENCRYPT=false\n";
        $envContent .= "SESSION_PATH=/\n";
        $envContent .= "SESSION_DOMAIN=null\n\n";
        $envContent .= "BROADCAST_CONNECTION=log\n";
        $envContent .= "FILESYSTEM_DISK=local\n";
        $envContent .= "QUEUE_CONNECTION=database\n\n";
        $envContent .= "CACHE_STORE=database\n\n";
        $envContent .= "MAIL_MAILER=log\n";
        $envContent .= "MAIL_FROM_ADDRESS=\"noreply@butimba.ac.tz\"\n";
        $envContent .= "MAIL_FROM_NAME=\"\${APP_NAME}\"\n\n";
        $envContent .= "VITE_APP_NAME=\"\${APP_NAME}\"\n";

        File::put(base_path('.env'), $envContent);
    }
}
