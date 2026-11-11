<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Feedback sent from the feedback page. Admins reply by email; the inbox only tracks read and archived.
 */
class InboxController extends Controller
{
    public const FOLDERS = ['inbox', 'unread', 'archived'];

    public function index(Request $request): Response
    {
        $folder = in_array($request->query('folder'), self::FOLDERS, true) ? $request->query('folder') : 'inbox';
        $category = in_array($request->query('category'), Feedback::CATEGORIES, true) ? $request->query('category') : null;

        $messages = match ($folder) {
            'unread' => Feedback::unread(),
            'archived' => Feedback::whereNotNull('archived_at'),
            default => Feedback::inbox(),
        };

        $messages = $messages
            ->when($category, fn ($query) => $query->where('category', $category))
            ->with('user:id,name,email,premium_until')
            ->latest()->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Feedback $feedback) => [
                'id' => $feedback->id,
                'category' => $feedback->category,
                'message' => $feedback->message,
                'page_url' => $feedback->page_url,
                'user_agent' => $feedback->user_agent,
                'has_screenshot' => $feedback->screenshot_path !== null,
                'read' => $feedback->read_at !== null,
                'archived' => $feedback->archived_at !== null,
                'created_at' => $feedback->created_at->toIso8601String(),
                'user' => $feedback->user ? [
                    'id' => $feedback->user->id,
                    'name' => $feedback->user->name,
                    'email' => $feedback->user->email,
                    'premium' => $feedback->user->isPremium(),
                ] : null,
            ]);

        return Inertia::render('admin/Inbox', [
            'messages' => $messages,
            'filters' => ['folder' => $folder, 'category' => $category],
            'counts' => [
                'inbox' => Feedback::inbox()->count(),
                'unread' => Feedback::unread()->count(),
                'archived' => Feedback::whereNotNull('archived_at')->count(),
            ],
        ]);
    }

    /**
     * Mark read or unread, archive or move back to the inbox.
     */
    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        $validated = $request->validate([
            'read' => ['sometimes', 'boolean'],
            'archived' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('read', $validated)) {
            $feedback->read_at = $validated['read'] ? ($feedback->read_at ?? now()) : null;
        }

        if (array_key_exists('archived', $validated)) {
            $feedback->archived_at = $validated['archived'] ? ($feedback->archived_at ?? now()) : null;
            // Archiving counts as reading it
            $feedback->read_at ??= $validated['archived'] ? now() : null;
        }

        $feedback->save();

        return back();
    }

    public function destroy(Feedback $feedback): RedirectResponse
    {
        $feedback->delete();

        return back();
    }

    public function screenshot(Feedback $feedback, PrivateFiles $files): HttpResponse
    {
        abort_unless($feedback->screenshot_path, 404);

        return $files->stream($feedback->screenshot_path, 'image/webp', "feedback {$feedback->id}");
    }
}
