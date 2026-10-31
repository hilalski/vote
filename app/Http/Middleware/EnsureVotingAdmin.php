<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureVotingAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('web')->user();

        if ($admin === null) {
            return redirect()->route('admin.login');
        }

        if (! $admin instanceof User || ! $admin->is_active || ! $admin->is_admin) {
            abort(403);
        }

        return $next($request);
    }
}
