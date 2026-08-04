<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class CheckInstalled
{
    public function handle(Request $request, Closure $next)
    {
        $installed = File::exists(base_path('storage/app/installed'));

        // If not installed and trying to access anything other than installer, redirect to installer
        if (!$installed && !$request->is('installer*') && !$request->is('api*') && !$request->is('up')) {
            return redirect('/installer');
        }

        // If installed and trying to access installer, redirect to home
        if ($installed && $request->is('installer*')) {
            return redirect('/');
        }

        return $next($request);
    }
}
