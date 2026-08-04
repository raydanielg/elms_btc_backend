<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    protected static array $permissions = [
        'program.manage'       => ['SUPER_ADMIN', 'REGISTRAR'],
        'course.manage'        => ['SUPER_ADMIN', 'REGISTRAR'],
        'course.teach'         => ['TUTOR'],
        'enrollment.manage'    => ['REGISTRAR'],
        'application.review'   => ['REGISTRAR'],
        'fee.manage'           => ['ACCOUNTANT', 'SUPER_ADMIN'],
        'payment.verify'       => ['ACCOUNTANT'],
        'result.manage'        => ['TUTOR', 'REGISTRAR'],
        'home.message'         => ['PRINCIPAL'],
        'lms.learn'            => ['STUDENT'],
        'user.manage'          => ['SUPER_ADMIN'],
        'media.manage'         => ['SUPER_ADMIN', 'PRINCIPAL'],
        'audit.view'           => ['SUPER_ADMIN'],
    ];

    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'You must be logged in.'],
            ], 401);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to perform this action.'],
            ], 403);
        }

        return $next($request);
    }

    public static function can(string $permission, string $role): bool
    {
        $allowed = self::$permissions[$permission] ?? [];
        return in_array($role, $allowed);
    }

    public static function hasPermission(User $user, string $permission): bool
    {
        return self::can($permission, $user->role);
    }
}
