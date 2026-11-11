<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The feedback page: bugs, ideas and questions, read by admins in the inbox at /admin/inbox.
 */
class FeedbackController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('feedback/Create', [
            // The page the user came from, when the link passed it (?from=/pigeons/12)
            'from' => self::appPath($request->query('from')),
            'recent' => $request->user()->feedback()->latest()->latest('id')->take(5)
                ->get(['id', 'category', 'message', 'read_at', 'created_at'])
                ->map(fn (Feedback $feedback) => [
                    'id' => $feedback->id,
                    'category' => $feedback->category,
                    'message' => Str::limit($feedback->message, 160),
                    'seen' => $feedback->read_at !== null,
                    'created_at' => $feedback->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request, PrivateFiles $files): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(Feedback::CATEGORIES)],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'screenshot' => ['nullable', ...PrivateFiles::imageRules()],
        ], [
            'screenshot.mimes' => 'The screenshot must be an image (JPG, PNG, WebP or GIF).',
            'screenshot.max' => 'The screenshot must be smaller than 10 MB.',
        ]);

        $screenshot = $request->hasFile('screenshot')
            ? $files->store($request->file('screenshot'), 'feedback/'.now()->format('Y/m'), field: 'screenshot')
            : null;

        $request->user()->feedback()->create([
            'category' => $validated['category'],
            'message' => trim($validated['message']),
            'page_url' => self::appPath($validated['page_url'] ?? null),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'screenshot_path' => $screenshot['path'] ?? null,
        ]);

        return back();
    }

    /**
     * A path inside the app ("/pigeons/12?tab=x"), or null for anything else, so the inbox only ever links back
     * into the app.
     */
    private static function appPath(mixed $value): ?string
    {
        if (! is_string($value) || ! str_starts_with($value, '/') || str_starts_with($value, '//') || str_contains($value, '\\')) {
            return null;
        }

        return Str::limit($value, 2000, '');
    }
}
