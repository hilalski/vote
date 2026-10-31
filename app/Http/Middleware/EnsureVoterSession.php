<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVoterSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $voterId = $request->session()->get('voting_voter_id');
        $voter = $voterId === null
            ? null
            : User::query()->whereKey($voterId)->where('is_active', true)->first();

        if ($voter === null) {
            $request->session()->forget(['voting_voter_id', 'voting_authenticated']);

            return redirect()->route('voting.index')
                ->with('error', 'Sesi voting Anda telah berakhir. Masukkan token untuk melanjutkan.');
        }

        $request->attributes->set('voting_voter', $voter);

        return $next($request);
    }
}
