<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * Usage: ->middleware('role:super_admin') or ->middleware('role:dispatcher,super_admin')
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $allowed = array_map(
            fn (string $role) => UserRole::from($role),
            $roles,
        );

        abort_unless(in_array($user->role, $allowed, strict: true), 403, 'This action is unauthorized.');

        return $next($request);
    }
}
