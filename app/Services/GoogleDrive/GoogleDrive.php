<?php

namespace App\Services\GoogleDrive;

use App\Models\GoogleDriveConnection;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The few Google OAuth and Drive v3 calls the app needs, made with Laravel's HTTP client rather than the Google SDK.
 *
 * Only the drive.file scope is requested: the app can see and change the files it created and nothing else in the
 * user's Drive. Google classes that scope as non-sensitive, so the OAuth app needs no security assessment.
 */
class GoogleDrive
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public const FOLDER_NAME = 'BloodlineHub';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD_API = 'https://www.googleapis.com/upload/drive/v3';

    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    /** @var array<int, string> Folder ids already checked during this request, by connection id */
    private array $checkedFolders = [];

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.google.redirect') ?: route('storage.google.callback');
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            // A refresh token, so photos can be served while the user is away
            'access_type' => 'offline',
            // Always ask, so Google also returns a refresh token when someone reconnects
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /**
     * Swap the code from the OAuth callback for tokens.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scope: string}
     */
    public function exchangeCode(string $code): array
    {
        $response = $this->send(fn () => Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]));

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            Log::warning('Google OAuth code exchange failed', ['status' => $response->status(), 'error' => $response->json('error')]);

            throw new GoogleDriveException('Google did not accept the sign-in. Please try connecting again.');
        }

        return [
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'expires_in' => (int) $response->json('expires_in', 3600),
            'scope' => (string) $response->json('scope', ''),
        ];
    }

    /**
     * A valid access token for the connection, refreshed when it is about to expire.
     */
    public function accessToken(GoogleDriveConnection $connection): string
    {
        if ($connection->access_token && $connection->access_token_expires_at?->isAfter(now()->addMinute())) {
            return $connection->access_token;
        }

        $response = $this->send(fn () => Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]));

        if ($response->json('error') === 'invalid_grant') {
            // Access was removed from the Google account (or the token expired): forget the connection so the user
            // is asked to connect again. Their DriveFile rows stay, so photos come back if they reconnect.
            $connection->delete();

            throw new GoogleDriveException('Your Google Drive connection has expired. Reconnect it in Settings → Storage.');
        }

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw new GoogleDriveException('Google Drive is not responding right now. Please try again.');
        }

        $connection->forceFill([
            'access_token' => $response->json('access_token'),
            'access_token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ])->save();

        return $connection->access_token;
    }

    /**
     * The Google account's email and Drive storage numbers (limit is null for unlimited storage).
     *
     * @return array{email: ?string, usage: ?int, limit: ?int}
     */
    public function about(GoogleDriveConnection $connection): array
    {
        $response = $this->send(fn () => $this->drive($connection)->get(self::API.'/about', [
            'fields' => 'user(emailAddress),storageQuota(limit,usage)',
        ]));

        $this->throwIfFailed($connection, $response);

        $limit = $response->json('storageQuota.limit');
        $usage = $response->json('storageQuota.usage');

        return [
            'email' => $response->json('user.emailAddress'),
            'usage' => is_numeric($usage) ? (int) $usage : null,
            'limit' => is_numeric($limit) ? (int) $limit : null,
        ];
    }

    /**
     * The app's folder in the user's Drive. Reuses the folder from an earlier connection when the app can still see
     * it, and makes a new one when it was deleted or trashed.
     */
    public function ensureFolder(GoogleDriveConnection $connection): string
    {
        if ($connection->folder_id && ($this->checkedFolders[$connection->id] ?? null) === $connection->folder_id) {
            return $connection->folder_id;
        }

        if ($connection->folder_id) {
            $response = $this->send(fn () => $this->drive($connection)->get(self::API.'/files/'.rawurlencode($connection->folder_id), [
                'fields' => 'id,trashed',
            ]));

            if ($response->successful() && ! $response->json('trashed')) {
                return $this->checkedFolders[$connection->id] = $connection->folder_id;
            }

            if ($response->status() !== 404) {
                $this->throwIfFailed($connection, $response);
            }
        }

        // With drive.file the search only sees folders this app created, never the user's own
        $response = $this->send(fn () => $this->drive($connection)->get(self::API.'/files', [
            'q' => "name = '".self::FOLDER_NAME."' and mimeType = '".self::FOLDER_MIME."' and trashed = false",
            'fields' => 'files(id)',
            'pageSize' => 1,
            'spaces' => 'drive',
        ]));
        $this->throwIfFailed($connection, $response);

        $folderId = $response->json('files.0.id');

        if (! is_string($folderId)) {
            $response = $this->send(fn () => $this->drive($connection)->post(self::API.'/files?fields=id', [
                'name' => self::FOLDER_NAME,
                'mimeType' => self::FOLDER_MIME,
                'description' => 'Pigeon photos and pedigree images saved by BloodlineHub.',
            ]));
            $this->throwIfFailed($connection, $response);

            $folderId = (string) $response->json('id');
        }

        $connection->forceFill(['folder_id' => $folderId])->save();

        return $this->checkedFolders[$connection->id] = $folderId;
    }

    /**
     * Save a file in the app's folder.
     *
     * @return array{id: string, size: int}
     */
    public function upload(GoogleDriveConnection $connection, string $name, string $contents, string $mimeType): array
    {
        $folderId = $this->ensureFolder($connection);

        $boundary = 'bloodlinehub-'.Str::random(24);
        $metadata = json_encode([
            'name' => $name,
            'parents' => [$folderId],
            'mimeType' => $mimeType,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$metadata}\r\n"
            ."--{$boundary}\r\nContent-Type: {$mimeType}\r\n\r\n{$contents}\r\n"
            ."--{$boundary}--";

        $response = $this->send(fn () => $this->drive($connection)
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post(self::UPLOAD_API.'/files?uploadType=multipart&fields=id,size'));

        if ($response->status() === 403 && $this->errorReason($response) === 'storageQuotaExceeded') {
            throw new GoogleDriveException('Your Google Drive is full. Free up space or add Google storage, then try again.');
        }

        $this->throwIfFailed($connection, $response);

        return [
            'id' => (string) $response->json('id'),
            'size' => (int) $response->json('size', strlen($contents)),
        ];
    }

    /**
     * The file's contents, or null when it no longer exists (deleted from Drive by the user).
     */
    public function download(GoogleDriveConnection $connection, string $fileId): ?string
    {
        $response = $this->send(fn () => $this->drive($connection)->get(self::API.'/files/'.rawurlencode($fileId), [
            'alt' => 'media',
        ]));

        if ($response->status() === 404) {
            return null;
        }

        $this->throwIfFailed($connection, $response);

        return $response->body();
    }

    /**
     * Move a file to the Drive trash, where the user can still restore it for 30 days.
     */
    public function trash(GoogleDriveConnection $connection, string $fileId): void
    {
        $response = $this->send(fn () => $this->drive($connection)->patch(self::API.'/files/'.rawurlencode($fileId).'?fields=id', [
            'trashed' => true,
        ]));

        if ($response->status() === 404) {
            return;
        }

        $this->throwIfFailed($connection, $response);
    }

    private function drive(GoogleDriveConnection $connection): PendingRequest
    {
        return Http::withToken($this->accessToken($connection))->timeout(20);
    }

    /**
     * @param  Closure(): Response  $request
     */
    private function send(Closure $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            Log::warning('Google Drive request failed', ['message' => $e->getMessage()]);

            throw new GoogleDriveException('Could not reach Google Drive. Please try again.');
        }
    }

    private function throwIfFailed(GoogleDriveConnection $connection, Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        Log::warning('Google Drive API error', [
            'status' => $response->status(),
            'reason' => $this->errorReason($response),
            'connection_id' => $connection->id,
        ]);

        if ($response->status() === 401 && $connection->exists) {
            // The access token was rejected before it expired (access removed mid-hour): refresh on the next call
            $connection->forceFill(['access_token' => null, 'access_token_expires_at' => null])->save();
        }

        throw new GoogleDriveException(match (true) {
            $response->status() === 429, $this->errorReason($response) === 'rateLimitExceeded',
            $this->errorReason($response) === 'userRateLimitExceeded' => 'Google Drive is busy. Please try again in a minute.',
            $response->status() === 401, $response->status() === 403 => 'Google Drive refused the request. Try reconnecting it in Settings → Storage.',
            default => 'Google Drive is not responding right now. Please try again.',
        });
    }

    private function errorReason(Response $response): ?string
    {
        $reason = $response->json('error.errors.0.reason');

        return is_string($reason) ? $reason : null;
    }
}
