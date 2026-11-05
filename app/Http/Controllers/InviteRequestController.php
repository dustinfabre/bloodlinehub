<?php

namespace App\Http\Controllers;

use App\Models\InviteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Request an invite" page, shown at /register while registration is closed.
 *
 * Every accepted submission gets the same response, whether the email is new, already on the list, or the
 * honeypot was filled, so the form can't be used to find out which emails are on the list.
 */
class InviteRequestController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a hidden field people leave empty. Bots that fill it get a normal success and nothing is saved.
        if ($request->filled('website')) {
            return back();
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        InviteRequest::firstOrCreate([
            'email' => Str::lower(trim($validated['email'])),
        ]);

        return back();
    }
}
