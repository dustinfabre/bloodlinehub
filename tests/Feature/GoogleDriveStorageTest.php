<?php

namespace Tests\Feature;

use App\Models\DriveFile;
use App\Models\GoogleDriveConnection;
use App\Models\Pigeon;
use App\Models\User;
use App\Services\GoogleDrive\GoogleDrive;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Photos are saved to each user's own Google Drive and served back through /media. Google is faked throughout.
 */
class GoogleDriveStorageTest extends TestCase
{
    use RefreshDatabase;

    protected User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->me = User::factory()->create();

        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
            'services.google.redirect' => null,
        ]);
    }

    private function connectDrive(User $user, array $attributes = []): GoogleDriveConnection
    {
        return GoogleDriveConnection::create([
            'user_id' => $user->id,
            'google_email' => 'breeder@gmail.com',
            'refresh_token' => 'refresh-token',
            'access_token' => 'access-token',
            'access_token_expires_at' => now()->addHour(),
            'folder_id' => 'folder-1',
            ...$attributes,
        ]);
    }

    private function driveFile(User $user, string $driveId): DriveFile
    {
        return DriveFile::create(['user_id' => $user->id, 'drive_file_id' => $driveId, 'mime_type' => 'image/webp', 'size' => 10]);
    }

    /**
     * Google as the app sees it; $overrides come first so they win over the defaults.
     */
    private function fakeGoogle(array $overrides = []): void
    {
        Http::preventStrayRequests();
        Http::fake($overrides + [
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-access-token', 'expires_in' => 3599, 'token_type' => 'Bearer']),
            'www.googleapis.com/drive/v3/about*' => Http::response([
                'user' => ['emailAddress' => 'breeder@gmail.com'],
                'storageQuota' => ['limit' => '16106127360', 'usage' => '1073741824'],
            ]),
            'www.googleapis.com/drive/v3/files/folder-1*' => Http::response(['id' => 'folder-1', 'trashed' => false]),
            // Folder search (GET) and folder creation (POST)
            'www.googleapis.com/drive/v3/files?*' => fn (Request $request) => $request->method() === 'POST'
                ? Http::response(['id' => 'folder-new'])
                : Http::response(['files' => []]),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response(['id' => 'drive-new', 'size' => '2048']),
            'www.googleapis.com/drive/v3/files/drive-*' => fn (Request $request) => $request->method() === 'PATCH'
                ? Http::response(['id' => 'trashed'])
                : Http::response('webp-bytes', 200, ['Content-Type' => 'image/webp']),
        ]);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('bird.jpg', 40, 30);
    }

    public function test_connect_sends_the_user_to_google_asking_only_for_files_the_app_creates(): void
    {
        $response = $this->actingAs($this->me)
            ->get(route('storage.google.connect', ['return_to' => '/pigeons/5/edit']))
            ->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('https://www.googleapis.com/auth/drive.file', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame(route('storage.google.callback'), $query['redirect_uri']);
        $this->assertSame(session('google_drive.state'), $query['state']);
        $this->assertSame('/pigeons/5/edit', session('google_drive.return_to'));
    }

    public function test_connect_only_returns_to_pages_on_this_site(): void
    {
        foreach (['//evil.test/x', 'https://evil.test', '/\\evil.test', 'pigeons'] as $returnTo) {
            $this->actingAs($this->me)->get(route('storage.google.connect', ['return_to' => $returnTo]))->assertRedirect();

            $this->assertNull(session('google_drive.return_to'), $returnTo);
        }
    }

    public function test_connect_is_unavailable_without_google_credentials(): void
    {
        config(['services.google.client_id' => null]);

        $this->actingAs($this->me)->get(route('storage.google.connect'))->assertNotFound();
    }

    public function test_the_callback_saves_the_connection_and_returns_to_the_page_the_user_left(): void
    {
        $this->fakeGoogle([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'first-access',
                'refresh_token' => 'first-refresh',
                'expires_in' => 3599,
                'scope' => GoogleDrive::SCOPE,
            ]),
        ]);

        $this->actingAs($this->me)
            ->withSession(['google_drive.state' => 'state-1', 'google_drive.return_to' => '/pigeons/create'])
            ->get(route('storage.google.callback', ['state' => 'state-1', 'code' => 'auth-code']))
            ->assertRedirect('/pigeons/create');

        $connection = GoogleDriveConnection::where('user_id', $this->me->id)->sole();
        $this->assertSame('first-refresh', $connection->refresh_token);
        $this->assertSame('breeder@gmail.com', $connection->google_email);
        $this->assertSame('folder-new', $connection->folder_id);

        // Tokens are encrypted at rest
        $raw = DB::table('google_drive_connections')->where('id', $connection->id)->value('refresh_token');
        $this->assertNotSame('first-refresh', $raw);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['code'] === 'auth-code'
            && $request['grant_type'] === 'authorization_code');
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_starts_with($request->url(), 'https://www.googleapis.com/drive/v3/files?')
            && $request['name'] === 'BloodlineHub'
            && $request['mimeType'] === 'application/vnd.google-apps.folder');
    }

    public function test_reconnecting_reuses_the_folder_the_app_made_before(): void
    {
        $this->fakeGoogle([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3599, 'scope' => GoogleDrive::SCOPE]),
            'www.googleapis.com/drive/v3/files?*' => fn (Request $request) => $request->method() === 'POST'
                ? Http::response(['id' => 'folder-duplicate'])
                : Http::response(['files' => [['id' => 'folder-old']]]),
        ]);

        $this->actingAs($this->me)
            ->withSession(['google_drive.state' => 'state-1'])
            ->get(route('storage.google.callback', ['state' => 'state-1', 'code' => 'auth-code']))
            ->assertRedirect(route('storage.edit', absolute: false));

        $this->assertSame('folder-old', GoogleDriveConnection::where('user_id', $this->me->id)->value('folder_id'));
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/drive/v3/files'));
    }

    public function test_the_callback_rejects_a_forged_state(): void
    {
        $this->fakeGoogle();

        $this->actingAs($this->me)
            ->withSession(['google_drive.state' => 'state-1'])
            ->get(route('storage.google.callback', ['state' => 'other', 'code' => 'auth-code']))
            ->assertRedirect(route('storage.edit'))
            ->assertSessionHas('error');

        $this->assertSame(0, GoogleDriveConnection::count());
        Http::assertNothingSent();
    }

    public function test_the_callback_needs_the_drive_permission(): void
    {
        // Google lets people untick the Drive box on the consent screen
        $this->fakeGoogle([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3599, 'scope' => 'openid']),
        ]);

        $this->actingAs($this->me)
            ->withSession(['google_drive.state' => 'state-1'])
            ->get(route('storage.google.callback', ['state' => 'state-1', 'code' => 'auth-code']))
            ->assertRedirect(route('storage.edit'))
            ->assertSessionHas('error');

        $this->assertSame(0, GoogleDriveConnection::count());
    }

    public function test_photos_need_a_connected_drive(): void
    {
        $this->fakeGoogle();

        $this->actingAs($this->me)
            ->post(route('pigeons.store'), ['status' => 'stock', 'ring_number' => 'PH-1', 'photo' => $this->photo()])
            ->assertSessionHasErrors(['photo' => 'Connect your Google Drive in Settings → Storage to add photos.']);

        $this->assertSame(0, Pigeon::count());
        Http::assertNothingSent();
    }

    public function test_photos_are_saved_to_the_users_drive_and_served_from_the_app(): void
    {
        $this->connectDrive($this->me);
        $this->fakeGoogle();

        $this->actingAs($this->me)
            ->post(route('pigeons.store'), [
                'status' => 'stock',
                'ring_number' => 'ph-1',
                'photo' => $this->photo(),
                'pedigree_images' => [$this->photo(), $this->photo()],
            ])
            ->assertSessionHasNoErrors();

        $pigeon = Pigeon::where('ring_number', 'PH-1')->sole();
        $this->assertMatchesRegularExpression('#^/media/[0-9a-f-]{36}\.webp$#', $pigeon->photo_url);
        $this->assertCount(2, $pigeon->pedigree_images);
        $this->assertSame(3, DriveFile::where('user_id', $this->me->id)->where('drive_file_id', 'drive-new')->count());

        $uploads = Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'https://www.googleapis.com/upload/drive/v3/files'))->values();
        $this->assertCount(3, $uploads);
        [$request] = $uploads[0];
        $this->assertStringContainsString('uploadType=multipart', $request->url());
        $this->assertSame(['Bearer access-token'], $request->header('Authorization'));
        $this->assertStringContainsString('"name":"PH-1 photo.webp"', $request->body());
        $this->assertStringContainsString('"parents":["folder-1"]', $request->body());
        $this->assertStringContainsString('"name":"PH-1 pedigree 2.webp"', $uploads[2][0]->body());
    }

    public function test_an_expired_access_token_is_refreshed_before_uploading(): void
    {
        $connection = $this->connectDrive($this->me, ['access_token_expires_at' => now()->subMinute()]);
        $this->fakeGoogle();

        $this->actingAs($this->me)
            ->post(route('pigeons.store'), ['status' => 'stock', 'ring_number' => 'PH-1', 'photo' => $this->photo()])
            ->assertSessionHasNoErrors();

        $this->assertSame('new-access-token', $connection->fresh()->access_token);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'refresh-token');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/upload/drive/v3/files')
            && $request->header('Authorization') === ['Bearer new-access-token']);
    }

    public function test_access_removed_on_the_google_side_asks_the_user_to_reconnect(): void
    {
        $this->connectDrive($this->me, ['access_token_expires_at' => now()->subMinute()]);
        $this->fakeGoogle([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->actingAs($this->me)
            ->post(route('pigeons.store'), ['status' => 'stock', 'ring_number' => 'PH-1', 'photo' => $this->photo()])
            ->assertSessionHasErrors(['photo' => 'Your Google Drive connection has expired. Reconnect it in Settings → Storage.']);

        $this->assertSame(0, GoogleDriveConnection::count());
        $this->assertSame(0, Pigeon::count());
    }

    public function test_a_full_drive_is_explained(): void
    {
        $this->connectDrive($this->me);
        $this->fakeGoogle([
            'www.googleapis.com/upload/drive/v3/files*' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'storageQuotaExceeded']]]], 403),
        ]);

        $this->actingAs($this->me)
            ->post(route('pigeons.store'), ['status' => 'stock', 'ring_number' => 'PH-1', 'photo' => $this->photo()])
            ->assertSessionHasErrors(['photo' => 'Your Google Drive is full. Free up space or add Google storage, then try again.']);
    }

    public function test_media_is_served_from_the_owners_drive_with_no_cookies_and_long_caching(): void
    {
        $this->connectDrive($this->me);
        $file = $this->driveFile($this->me, 'drive-photo');
        $this->fakeGoogle();

        $response = $this->get($file->url())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('ETag', '"'.$file->id.'"');

        $this->assertSame('webp-bytes', $response->getContent());
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        // A CDN will not cache a response that sets cookies
        $this->assertSame([], $response->headers->getCookies());
        Http::assertSent(fn (Request $request) => $request->url() === 'https://www.googleapis.com/drive/v3/files/drive-photo?alt=media'
            && $request->header('Authorization') === ['Bearer access-token']);
    }

    public function test_a_cached_image_is_not_fetched_from_drive_again(): void
    {
        $this->connectDrive($this->me);
        $file = $this->driveFile($this->me, 'drive-photo');
        $this->fakeGoogle();

        $this->get($file->url(), ['If-None-Match' => '"'.$file->id.'"'])->assertStatus(304);

        Http::assertNothingSent();
    }

    public function test_media_is_unavailable_when_missing_or_the_owner_disconnected(): void
    {
        $file = $this->driveFile($this->me, 'drive-photo');
        $this->fakeGoogle();

        $this->get($file->url())->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/media/00000000-0000-4000-8000-000000000000.webp')->assertNotFound();
        $this->get('/media/not-a-uuid.webp')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_media_deleted_from_drive_by_the_user_is_not_found(): void
    {
        $this->connectDrive($this->me);
        $file = $this->driveFile($this->me, 'drive-photo');
        $this->fakeGoogle(['www.googleapis.com/drive/v3/files/drive-*' => Http::response(['error' => ['code' => 404]], 404)]);

        $this->get($file->url())->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_replacing_a_photo_trashes_the_old_one_once_the_new_one_is_saved(): void
    {
        $this->connectDrive($this->me);
        $old = $this->driveFile($this->me, 'drive-old');
        $pigeon = Pigeon::create(['user_id' => $this->me->id, 'status' => 'stock', 'ring_number' => 'PH-1', 'photo_url' => $old->url()]);
        $this->fakeGoogle();

        $this->actingAs($this->me)
            ->put(route('pigeons.update', $pigeon), ['status' => 'stock', 'ring_number' => 'PH-1', 'photo' => $this->photo()])
            ->assertSessionHasNoErrors();

        $this->assertNotSame($old->url(), $pigeon->fresh()->photo_url);
        $this->assertModelMissing($old);
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && str_starts_with($request->url(), 'https://www.googleapis.com/drive/v3/files/drive-old')
            && $request['trashed'] === true);
    }

    public function test_a_failed_upload_keeps_the_old_photo(): void
    {
        $this->connectDrive($this->me);
        $old = $this->driveFile($this->me, 'drive-old');
        $pigeon = Pigeon::create(['user_id' => $this->me->id, 'status' => 'stock', 'ring_number' => 'PH-1', 'photo_url' => $old->url()]);
        $this->fakeGoogle(['www.googleapis.com/upload/drive/v3/files*' => Http::response([], 500)]);

        $this->actingAs($this->me)
            ->put(route('pigeons.update', $pigeon), ['status' => 'stock', 'ring_number' => 'PH-1', 'photo' => $this->photo()])
            ->assertSessionHasErrors('photo');

        $this->assertSame($old->url(), $pigeon->fresh()->photo_url);
        $this->assertModelExists($old);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'PATCH');
    }

    public function test_deleting_a_pigeon_trashes_its_images(): void
    {
        $this->connectDrive($this->me);
        $photo = $this->driveFile($this->me, 'drive-photo');
        $pedigree = $this->driveFile($this->me, 'drive-pedigree');
        $pigeon = Pigeon::create([
            'user_id' => $this->me->id,
            'status' => 'stock',
            'ring_number' => 'PH-1',
            'photo_url' => $photo->url(),
            'pedigree_images' => [$pedigree->url()],
        ]);
        $this->fakeGoogle();

        $this->actingAs($this->me)->delete(route('pigeons.destroy', $pigeon))->assertRedirect(route('pigeons.index'));

        $this->assertSame(0, DriveFile::count());
        Http::assertSentCount(2);
    }

    public function test_only_my_own_images_can_be_removed(): void
    {
        $other = User::factory()->create();
        $this->connectDrive($other);
        $theirs = $this->driveFile($other, 'drive-theirs');
        $this->fakeGoogle();

        $this->assertFalse(app(ImageUploadService::class)->delete($theirs->url(), $this->me));

        $this->assertModelExists($theirs);
        Http::assertNothingSent();
    }

    public function test_disconnecting_keeps_the_images_for_a_later_reconnect(): void
    {
        $this->connectDrive($this->me);
        $file = $this->driveFile($this->me, 'drive-photo');
        $this->fakeGoogle();

        $this->actingAs($this->me)
            ->delete(route('storage.google.destroy'))
            ->assertRedirect(route('storage.edit'));

        $this->assertSame(0, GoogleDriveConnection::count());
        $this->assertModelExists($file);
        // Not revoked at Google: with drive.file a revoke can hide the app's files even after reconnecting
        Http::assertNothingSent();
    }

    public function test_the_storage_page_shows_the_connected_drive(): void
    {
        $this->connectDrive($this->me);
        $this->driveFile($this->me, 'drive-photo');
        $this->fakeGoogle();

        $this->actingAs($this->me)
            ->get(route('storage.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Storage')
                ->where('configured', true)
                ->where('drive.email', 'breeder@gmail.com')
                ->where('drive.usage', 1073741824)
                ->where('drive.limit', 16106127360)
                ->where('drive.folder_url', 'https://drive.google.com/drive/folders/folder-1')
                ->where('imageCount', 1));
    }

    public function test_the_pigeon_form_knows_whether_a_drive_is_connected(): void
    {
        $this->actingAs($this->me)
            ->get(route('pigeons.create'))
            ->assertInertia(fn (Assert $page) => $page->where('driveConnected', false));

        $this->connectDrive($this->me);

        $this->actingAs($this->me)
            ->get(route('pigeons.create'))
            ->assertInertia(fn (Assert $page) => $page->where('driveConnected', true));
    }
}
