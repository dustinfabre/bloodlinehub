<?php

namespace App\Http\Controllers;

use App\Http\Middleware\NoIndex;
use App\Models\Pedigree;
use App\Services\Pedigree\PedigreePreview;
use App\Services\PedigreeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The link preview picture of a public pedigree, which social media and chat apps show when its link is posted
 * (og:image, see PedigreeController::openGraph()). Like /media it runs without a session or cookies
 * (routes/media.php), so a CDN can cache it.
 *
 * Private and password-protected pedigrees have none: the picture shows the bird and its family.
 */
class PedigreePreviewController extends Controller
{
    public function __invoke(Request $request, string $serial, PedigreeService $pedigrees, PedigreePreview $preview): Response
    {
        $pedigree = Pedigree::with('pigeon.user')->where('serial', strtoupper($serial))->first();
        $pigeon = $pedigree?->pigeon;

        if (! $pigeon || ! $pedigree->is_public || ! $pigeon->user->hasPremium() || ! PedigreePreview::available()) {
            return response('', 404, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => NoIndex::DIRECTIVES]);
        }

        $card = $preview->card(
            $pedigrees->tree($pigeon, public: true, generations: 3),
            ['serial' => $pedigree->serial, ...$pedigrees->credits($pigeon)],
        );
        $etag = '"'.$preview->version($card).'"';

        // Kept for a day rather than for good: the owner may make the pedigree private again
        $cache = [
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => $etag,
            'X-Robots-Tag' => NoIndex::DIRECTIVES,
        ];

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, $cache);
        }

        [$png, $complete] = $preview->png($card);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($png),
            'X-Content-Type-Options' => 'nosniff',
            // Drawn without the bird's photo while Drive was unavailable: not kept, so the next fetch tries again
            ...($complete ? $cache : ['Cache-Control' => 'no-store', 'X-Robots-Tag' => NoIndex::DIRECTIVES]),
        ]);
    }
}
