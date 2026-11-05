<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Breeder details printed on every pedigree: the breeder or loft name, contact details and which of them to show.
 */
class PedigreeDetailsController extends Controller
{
    public function edit(Request $request): Response
    {
        $profile = $request->user()->breederProfile;

        return Inertia::render('settings/PedigreeDetails', [
            'accountName' => $request->user()->name,
            'details' => [
                'display_name' => $profile?->display_name,
                'phone' => $profile?->phone,
                'email' => $profile?->email,
                'address' => $profile?->address,
                'show_phone' => $profile?->show_phone ?? true,
                'show_email' => $profile?->show_email ?? true,
                'show_address' => $profile?->show_address ?? true,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-.\/\s]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:300'],
            'show_phone' => ['required', 'boolean'],
            'show_email' => ['required', 'boolean'],
            'show_address' => ['required', 'boolean'],
        ], [
            'phone.regex' => 'Use digits, spaces and + ( ) - . / only.',
        ]);

        $request->user()->breederProfile()->updateOrCreate([], $validated);

        return to_route('pedigree-details.edit');
    }
}
