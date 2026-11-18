<?php

namespace Tests\Feature;

use App\Enums\MeritType;
use App\Models\DriveFile;
use App\Models\GoogleDriveConnection;
use App\Models\Pigeon;
use App\Models\User;
use App\Services\GoogleDrive\GoogleDrive;
use App\Services\GoogleDrive\GoogleDriveException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Link previews of shared pedigrees: the Open Graph tags on /p/{serial} and the picture they point to.
 */
class PedigreePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Pigeon $pigeon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->user = User::factory()->premium()->create();
        $sire = Pigeon::create([
            'user_id' => $this->user->id,
            'status' => 'stock',
            'gender' => 'male',
            'ring_number' => 'BELG-2019-6123456',
            'name' => 'Golden Prince',
        ]);
        $sire->merits()->create(['type' => MeritType::AcePigeon, 'year' => 2021]);

        $this->pigeon = Pigeon::create([
            'user_id' => $this->user->id,
            'status' => 'stock',
            'gender' => 'male',
            'ring_number' => 'PHA-2025-04821',
            'name' => 'Blue Thunder',
            'bloodline' => 'Janssen',
            'color' => 'Blue Bar',
            'sire_id' => $sire->id,
            'dam_ring_number' => 'PHA-2021-00314',
            'dam_name' => 'Miss Batangas',
        ]);
        $this->pigeon->merits()->create(['type' => MeritType::RaceWinner, 'year' => 2025]);
    }

    private function makePublic(): string
    {
        $pedigree = $this->pigeon->pedigreeRecord();
        $pedigree->update(['is_public' => true]);

        return $pedigree->serial;
    }

    private function meta(TestResponse $response, string $property): ?string
    {
        $attribute = str_starts_with($property, 'twitter:') ? 'name' : 'property';

        return preg_match('#<meta '.$attribute.'="'.preg_quote($property, '#').'" content="([^"]*)">#', $response->getContent(), $match)
            ? htmlspecialchars_decode($match[1])
            : null;
    }

    private function previewUrl(string $serial): string
    {
        $image = $this->meta($this->get(route('pedigrees.shared', $serial))->assertOk(), 'og:image');
        $this->assertNotNull($image);

        return $image;
    }

    public function test_a_public_pedigree_link_previews_the_pedigree(): void
    {
        $serial = $this->makePublic();

        $page = $this->get(route('pedigrees.shared', $serial))->assertOk();

        $this->assertSame(route('pedigrees.shared', $serial), $this->meta($page, 'og:url'));
        $this->assertSame('Pedigree PHA-2025-04821 “Blue Thunder”', $this->meta($page, 'og:title'));
        $this->assertSame(
            // The dam isn't a loft bird, so the user is the bird's owner rather than its breeder
            'Janssen · Owned by '.$this->user->name.' · Sire BELG-2019-6123456 “Golden Prince” × Dam PHA-2021-00314 “Miss Batangas”. Tap any bird to see its merits, photos and bloodline.',
            $this->meta($page, 'og:description'),
        );
        $this->assertStringStartsWith(route('pedigrees.preview', $serial).'?v=', $this->meta($page, 'og:image'));
        $this->assertSame('1200', $this->meta($page, 'og:image:width'));
        $this->assertSame('630', $this->meta($page, 'og:image:height'));
        $this->assertSame('summary_large_image', $this->meta($page, 'twitter:card'));
    }

    public function test_the_preview_picture_is_a_png_served_without_a_session(): void
    {
        $serial = $this->makePublic();
        $url = $this->previewUrl($serial);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $response = $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('ETag', '"'.$query['v'].'"')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, noimageindex');

        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertSame([], $response->headers->getCookies());

        $size = getimagesizefromstring($response->getContent());
        $this->assertSame([1200, 630], [$size[0], $size[1]]);

        // Apps that already have it are told it hasn't changed
        $this->get($url, ['If-None-Match' => '"'.$query['v'].'"'])->assertStatus(304);
    }

    public function test_the_preview_link_changes_when_the_pedigree_does(): void
    {
        $serial = $this->makePublic();
        $before = $this->previewUrl($serial);

        $this->pigeon->sire->merits()->create(['type' => MeritType::Olympiad, 'year' => 2022]);

        $this->assertNotSame($before, $this->previewUrl($serial));
    }

    public function test_private_and_password_protected_pedigrees_have_no_preview_picture(): void
    {
        $pedigree = $this->pigeon->pedigreeRecord();

        $page = $this->get(route('pedigrees.shared', $pedigree->serial))->assertForbidden();
        $this->assertSame("Pedigree {$pedigree->serial}", $this->meta($page, 'og:title'));
        $this->assertSame('This pedigree is private.', $this->meta($page, 'og:description'));
        $this->assertNull($this->meta($page, 'og:image'));
        $this->assertStringNotContainsString('Blue Thunder', (string) $this->meta($page, 'og:description'));
        $this->get(route('pedigrees.preview', $pedigree->serial))->assertNotFound();

        // Not even when the owner opens it
        $page = $this->actingAs($this->user)->get(route('pedigrees.shared', $pedigree->serial))->assertOk();
        $this->assertNull($this->meta($page, 'og:image'));
        $this->assertSame("Pedigree {$pedigree->serial}", $this->meta($page, 'og:title'));
        auth()->logout();

        $pedigree->update(['password' => 'pigeon1234']);
        $page = $this->get(route('pedigrees.shared', $pedigree->serial))->assertForbidden();
        $this->assertStringContainsString('password protected', $this->meta($page, 'og:description'));
        $this->assertNull($this->meta($page, 'og:image'));
        $this->get(route('pedigrees.preview', $pedigree->serial))->assertNotFound();
    }

    public function test_pedigrees_of_accounts_without_premium_have_no_preview_picture(): void
    {
        $serial = $this->makePublic();
        $this->user->forceFill(['premium_until' => null, 'trial_ends_at' => null])->save();

        $page = $this->get(route('pedigrees.shared', $serial))->assertForbidden();
        $this->assertSame('This pedigree is private.', $this->meta($page, 'og:description'));
        $this->assertNull($this->meta($page, 'og:image'));
        $this->get(route('pedigrees.preview', $serial))->assertNotFound();
        $this->get(route('pedigrees.preview', 'BLH-NONE-XIST'))->assertNotFound();
    }

    public function test_preview_text_is_escaped(): void
    {
        $this->pigeon->update(['name' => '"><script>alert(1)</script>']);
        $serial = $this->makePublic();

        $page = $this->get(route('pedigrees.shared', $serial))->assertOk();

        $this->assertStringNotContainsString('"><script>alert(1)', $page->getContent());
        $this->assertSame('Pedigree PHA-2025-04821 “"><script>alert(1)</script>”', $this->meta($page, 'og:title'));
    }

    public function test_the_preview_shows_the_birds_photo_and_is_drawn_once(): void
    {
        $connection = GoogleDriveConnection::create([
            'user_id' => $this->user->id,
            'google_email' => 'breeder@gmail.com',
            'refresh_token' => 'refresh-token',
            'access_token' => 'access-token',
            'access_token_expires_at' => now()->addHour(),
            'folder_id' => 'folder-1',
        ]);
        $file = DriveFile::create(['user_id' => $this->user->id, 'drive_file_id' => 'photo-1', 'mime_type' => 'image/webp', 'size' => 10]);
        $this->pigeon->update(['photo_url' => $file->url()]);

        $photo = imagecreatetruecolor(40, 30);
        ob_start();
        imagewebp($photo);
        $webp = ob_get_clean();

        $this->mock(GoogleDrive::class)
            ->shouldReceive('download')
            ->once()
            ->withArgs(fn (GoogleDriveConnection $given, string $id) => $given->is($connection) && $id === 'photo-1')
            ->andReturn($webp);

        $url = $this->previewUrl($this->makePublic());

        $first = $this->get($url)->assertOk()->getContent();
        $this->assertSame($first, $this->get($url)->assertOk()->getContent());
    }

    public function test_a_preview_drawn_while_drive_is_down_is_not_kept(): void
    {
        GoogleDriveConnection::create([
            'user_id' => $this->user->id,
            'google_email' => 'breeder@gmail.com',
            'refresh_token' => 'refresh-token',
            'access_token' => 'access-token',
            'access_token_expires_at' => now()->addHour(),
            'folder_id' => 'folder-1',
        ]);
        $file = DriveFile::create(['user_id' => $this->user->id, 'drive_file_id' => 'photo-1', 'mime_type' => 'image/webp', 'size' => 10]);
        $this->pigeon->update(['photo_url' => $file->url()]);

        $this->mock(GoogleDrive::class)
            ->shouldReceive('download')
            ->twice()
            ->andThrow(new GoogleDriveException('Drive is down'));

        $url = $this->previewUrl($this->makePublic());

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
        $this->get($url)->assertOk();
    }
}
