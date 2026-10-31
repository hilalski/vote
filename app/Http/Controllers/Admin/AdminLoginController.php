<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('web')->check() && Auth::guard('web')->user()?->is_admin) {
            return redirect()->route('admin.voting.dashboard');
        }

        return view('admin.login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $credentials['is_admin'] = true;
        $credentials['is_active'] = true;

        if (! Auth::guard('web')->attempt($credentials)) {
            return back()->withInput($request->safe()->only(['email']))
                ->with('error', 'Email atau kata sandi admin tidak valid.');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.voting.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->forget('password_hash_web');
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
