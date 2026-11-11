<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Sign-up. Fortify's own registration is off (config/fortify.php) so this route can add a honeypot, a rate limit and
 * the terms checkbox; the account itself is still made by Fortify's CreateNewUser action.
 */
class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Register', [
            'trialDays' => Plan::premium()?->trial_days ?? 0,
        ]);
    }

    public function store(Request $request, CreatesNewUsers $creator): RedirectResponse
    {
        // Honeypot: a hidden field people leave empty. Bots that fill it are sent home and nothing is saved.
        if ($request->filled('website')) {
            return redirect()->route('home');
        }

        $request->validate(
            ['terms' => ['accepted']],
            ['terms.accepted' => 'Please agree to the Terms and Privacy Policy to create an account.'],
        );

        $user = $creator->create($request->only(['name', 'email', 'password', 'password_confirmation']));

        // New accounts start with the free trial of Premium, when there is one
        $user->startTrial(Plan::premium()?->trial_days ?? 0);

        event(new Registered($user));

        // Remembered, like the login form's default, so the installed app stays signed in
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
