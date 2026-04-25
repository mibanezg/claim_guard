<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LandlordLoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Landlord/Login', [
            'flash' => session()->only(['error']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->with('error', 'Credenciales incorrectas.');
        }

        $user = Auth::user();

        if (!$user->is_super_admin) {
            Auth::logout();
            return back()->with('error', 'No tienes permiso para acceder al panel landlord.');
        }

        $request->session()->regenerate();

        return redirect()->route('landlord.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landlord.login');
    }
}
