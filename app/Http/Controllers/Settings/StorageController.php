<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\DriveFile;
use App\Models\GoogleDriveConnection;
use App\Services\GoogleDrive\GoogleDrive;
use App\Services\GoogleDrive\GoogleDriveException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Settings → Storage: connect the Google Drive where the user's photos are saved.
 */
class StorageController extends Controller
{
    public function show(Request $request, GoogleDrive $drive): Response
    {
        $user = $request->user();
        $connection = GoogleDriveConnection::forUser($user);
        $about = null;

        if ($connection) {
            try {
                $about = $drive->about($connection);
            } catch (GoogleDriveException) {
                // Shown without the storage numbers; if access was removed, about() already dropped the connection
            }
        }

        return Inertia::render('settings/Storage', [
            'configured' => $drive->isConfigured(),
            'drive' => $connection?->exists ? [
                'email' => $about['email'] ?? $connection->google_email,
                'folder_url' => $connection->folderUrl(),
                'connected_at' => $connection->created_at?->toIso8601String(),
                'usage' => $about['usage'] ?? null,
                'limit' => $about['limit'] ?? null,
            ] : null,
            'imageCount' => DriveFile::where('user_id', $user->id)->count(),
            'status' => $request->session()->get('status'),
            'error' => $request->session()->get('error'),
        ]);
    }

    /**
     * Send the user to Google's consent screen.
     */
    public function connect(Request $request, GoogleDrive $drive): SymfonyResponse
    {
        abort_unless($drive->isConfigured(), 404);

        $state = Str::random(40);
        $request->session()->put('google_drive.state', $state);

        // Come back to the page the user left (such as a half-edited pigeon); only paths on this site
        $returnTo = $request->query('return_to');
        if (is_string($returnTo) && preg_match('#^/(?![/\\\\])#', $returnTo) && ! str_contains($returnTo, '\\')) {
            $request->session()->put('google_drive.return_to', $returnTo);
        } else {
            $request->session()->forget('google_drive.return_to');
        }

        return Inertia::location($drive->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleDrive $drive): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_drive.state');
        $returnTo = $request->session()->pull('google_drive.return_to');

        if (! is_string($expectedState) || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return $this->failed('That Google sign-in has expired. Please try connecting again.');
        }

        $code = $request->query('code');

        if ($request->query('error') || ! is_string($code) || $code === '') {
            return $this->failed('Google Drive was not connected.');
        }

        try {
            $tokens = $drive->exchangeCode($code);
        } catch (GoogleDriveException $e) {
            return $this->failed($e->getMessage());
        }

        // Google lets people untick the Drive permission on the consent screen
        if (! in_array(GoogleDrive::SCOPE, explode(' ', $tokens['scope']), true)) {
            return $this->failed('BloodlineHub needs permission to save files to your Google Drive. Please connect again and leave that box ticked.');
        }

        if (! $tokens['refresh_token']) {
            return $this->failed('Google did not grant lasting access. Please try connecting again.');
        }

        $connection = GoogleDriveConnection::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'refresh_token' => $tokens['refresh_token'],
                'access_token' => $tokens['access_token'],
                'access_token_expires_at' => now()->addSeconds($tokens['expires_in']),
                // May be a different Google account than last time: look the folder up again
                'folder_id' => null,
                'google_email' => null,
            ],
        );

        try {
            $connection->forceFill(['google_email' => $drive->about($connection)['email']])->save();
            $drive->ensureFolder($connection);
        } catch (GoogleDriveException) {
            // Connected anyway; the folder is made on the first upload
        }

        return redirect($returnTo ?? route('storage.edit', absolute: false))
            ->with('status', 'google-drive-connected')
            ->with('success', 'Google Drive connected. New photos are saved to the BloodlineHub folder in your Drive.');
    }

    /**
     * Forget the user's Drive tokens. The Google grant is deliberately not revoked: with the drive.file scope a
     * revoke can hide the files the app already created even after reconnecting, so the user's photos would be lost
     * to the app for good. The user can still remove access from their Google account themselves.
     */
    public function destroy(Request $request): RedirectResponse
    {
        GoogleDriveConnection::where('user_id', $request->user()->id)->delete();

        return to_route('storage.edit')->with('status', 'google-drive-disconnected');
    }

    private function failed(string $message): RedirectResponse
    {
        return to_route('storage.edit')->with('error', $message);
    }
}
