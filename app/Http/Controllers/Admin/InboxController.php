<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\FeedbackLabel;
use App\Models\User;
use App\Notifications\FeedbackStatusChanged;
use App\Services\MailInBackground;
use App\Services\PrivateFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Feedback sent from the feedback page. Admins star messages, give them a status that fits their category (a bug is
 * fixed, an idea planned), label them, keep private notes, and mark repeats as "same as" an earlier message so
 * popular requests show how many people asked. Changing the status can email the senders.
 */
class InboxController extends Controller
{
    public const FOLDERS = ['inbox', 'unread', 'starred', 'archived'];

    public function index(Request $request): Response
    {
        $folder = in_array($request->query('folder'), self::FOLDERS, true) ? $request->query('folder') : 'inbox';
        $category = in_array($request->query('category'), Feedback::CATEGORIES, true) ? $request->query('category') : null;
        $status = $this->statusFilter($request->query('status'), $category);
        $label = FeedbackLabel::find((int) $request->query('label'))?->id;
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $sort = $request->query('sort') === 'requested' ? 'requested' : 'newest';

        $messages = match ($folder) {
            'unread' => Feedback::unread(),
            // Starred messages stay findable after they are archived
            'starred' => Feedback::whereNotNull('starred_at'),
            'archived' => Feedback::whereNotNull('archived_at'),
            default => Feedback::inbox(),
        };

        $messages = $messages
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($status === 'open', fn ($query) => $query->open())
            ->when($status === 'closed', fn ($query) => $query->closed())
            ->when($status && ! in_array($status, ['open', 'closed'], true), fn ($query) => $query->where('status', $status))
            ->when($label, fn ($query) => $query->whereHas('labels', fn ($query) => $query->whereKey($label)))
            ->when($search !== '', fn ($query) => $this->search($query, $search))
            ->with([
                'user:id,name,email,premium_until',
                'labels:id',
                'duplicateOf:id,message,user_id',
                'duplicateOf.user:id,name',
                'duplicates' => fn ($query) => $query->select('id', 'duplicate_of_id', 'user_id', 'created_at')->oldest(),
                'duplicates.user:id,name',
            ])
            ->withCount('duplicates')
            ->when($sort === 'requested', fn ($query) => $query->orderByDesc('duplicates_count'))
            ->latest()->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Feedback $feedback) => $this->row($feedback));

        return Inertia::render('admin/Inbox', [
            'messages' => $messages,
            'filters' => [
                'folder' => $folder,
                'category' => $category,
                'status' => $status,
                'label' => $label,
                'q' => $search,
                'sort' => $sort,
            ],
            'counts' => [
                'inbox' => Feedback::inbox()->count(),
                'unread' => Feedback::unread()->count(),
                'starred' => Feedback::whereNotNull('starred_at')->count(),
                'archived' => Feedback::whereNotNull('archived_at')->count(),
            ],
            'labels' => FeedbackLabel::orderBy('name')->withCount('feedback')->get(['id', 'name', 'color']),
            'statuses' => Feedback::STATUSES,
            'closedStatuses' => Feedback::CLOSED,
            'labelColors' => FeedbackLabel::COLORS,
        ]);
    }

    /**
     * Mark read or unread, star, archive, keep a private note, or set the labels.
     */
    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        $validated = $request->validate([
            'read' => ['sometimes', 'boolean'],
            'archived' => ['sometimes', 'boolean'],
            'starred' => ['sometimes', 'boolean'],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'labels' => ['sometimes', 'array'],
            'labels.*' => ['integer', Rule::exists('feedback_labels', 'id')],
        ]);

        if (array_key_exists('read', $validated)) {
            $feedback->read_at = $validated['read'] ? ($feedback->read_at ?? now()) : null;
        }

        if (array_key_exists('archived', $validated)) {
            $feedback->archived_at = $validated['archived'] ? ($feedback->archived_at ?? now()) : null;
            // Archiving counts as reading it
            $feedback->read_at ??= $validated['archived'] ? now() : null;
        }

        if (array_key_exists('starred', $validated)) {
            $feedback->starred_at = $validated['starred'] ? ($feedback->starred_at ?? now()) : null;
        }

        if (array_key_exists('admin_note', $validated)) {
            $feedback->admin_note = filled($validated['admin_note']) ? trim($validated['admin_note']) : null;
        }

        $feedback->save();

        if (array_key_exists('labels', $validated)) {
            $feedback->labels()->sync($validated['labels']);
        }

        return back();
    }

    /**
     * Move a message on (e.g. a bug to Fixed). Its "me too" messages follow, and with `notify` everyone who sent them
     * is emailed, with the admin's optional note.
     */
    public function status(Request $request, Feedback $feedback, MailInBackground $mail): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys($feedback->statuses()))],
            'notify' => ['boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $notify = $request->boolean('notify');

        if ($notify && ! FeedbackStatusChanged::canNotify($validated['status'])) {
            throw ValidationException::withMessages(['notify' => 'Senders can\'t be emailed about this status.']);
        }

        DB::transaction(function () use ($feedback, $validated) {
            $changes = ['status' => $validated['status'], 'status_changed_at' => now()];
            $feedback->forceFill($changes)->save();
            $feedback->duplicates()->update($changes);
        });

        if ($notify) {
            $senders = User::whereIn('id', $feedback->duplicates()->pluck('user_id')->push($feedback->user_id))->get();

            if ($senders->isNotEmpty()) {
                $mail->send($senders, new FeedbackStatusChanged($feedback->fresh(), $validated['note'] ?? null));
            }
        }

        return back();
    }

    /**
     * Mark a message as asking the same as an earlier one ("me too"), or undo it with `duplicate_of` null. A repeat
     * is archived, and takes the earlier message's status; repeats of it move to the earlier message too, so there is
     * only ever one level.
     */
    public function duplicate(Request $request, Feedback $feedback): RedirectResponse
    {
        $validated = $request->validate([
            'duplicate_of' => ['nullable', 'integer', Rule::exists('feedback', 'id'), Rule::notIn([$feedback->id])],
        ], [
            'duplicate_of.not_in' => 'A message can\'t be the same as itself.',
        ]);

        if (! isset($validated['duplicate_of'])) {
            $feedback->forceFill(['duplicate_of_id' => null, 'archived_at' => null])->save();

            return back();
        }

        $original = Feedback::findOrFail($validated['duplicate_of']);
        // Point at the first message of the group
        $original = $original->duplicate_of_id ? Feedback::findOrFail($original->duplicate_of_id) : $original;

        if ($original->is($feedback)) {
            throw ValidationException::withMessages(['duplicate_of' => 'That message is already marked as the same as this one.']);
        }

        DB::transaction(function () use ($feedback, $original) {
            $feedback->duplicates()->update(['duplicate_of_id' => $original->id, 'status' => $original->status]);
            $feedback->forceFill([
                'duplicate_of_id' => $original->id,
                'status' => $original->status,
                'archived_at' => $feedback->archived_at ?? now(),
                'read_at' => $feedback->read_at ?? now(),
            ])->save();
        });

        return back();
    }

    /**
     * Messages to pick from for "Same as…": earlier messages that aren't repeats themselves, by text or number.
     */
    public function lookup(Request $request): JsonResponse
    {
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $exclude = (int) $request->query('exclude');

        $results = Feedback::query()
            ->whereNull('duplicate_of_id')
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude))
            ->when(in_array($request->query('category'), Feedback::CATEGORIES, true), fn ($query) => $query->where('category', $request->query('category')))
            ->when($search !== '', fn ($query) => $this->search($query, $search))
            ->with('user:id,name')
            ->withCount('duplicates')
            ->latest()->latest('id')
            ->take(10)
            ->get()
            ->map(fn (Feedback $feedback) => [
                'id' => $feedback->id,
                'category' => $feedback->category,
                'message' => Str::limit(Str::squish($feedback->message), 140),
                'user' => $feedback->user?->name,
                'requests' => $feedback->duplicates_count + 1,
                'created_at' => $feedback->created_at->toIso8601String(),
            ]);

        return response()->json(['results' => $results]);
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

    /**
     * "open", "closed", or one of the category's statuses (only with a category, as the words differ per category).
     */
    private function statusFilter(mixed $status, ?string $category): ?string
    {
        if (in_array($status, ['open', 'closed'], true)) {
            return $status;
        }

        return $category && is_string($status) && array_key_exists($status, Feedback::STATUSES[$category]) ? $status : null;
    }

    /**
     * Text in the message, the private note or the sender's name or email, or "#12" / "12" for a message number.
     */
    private function search(Builder $query, string $search): Builder
    {
        $like = '%'.addcslashes(Str::lower($search), '%_\\').'%';
        $number = ctype_digit(ltrim($search, '#')) ? (int) ltrim($search, '#') : null;

        return $query->where(fn ($query) => $query
            ->whereRaw('lower(message) like ?', [$like])
            ->orWhereRaw('lower(coalesce(admin_note, \'\')) like ?', [$like])
            ->orWhereHas('user', fn ($query) => $query
                ->whereRaw('lower(name) like ?', [$like])
                ->orWhereRaw('lower(email) like ?', [$like]))
            ->when($number, fn ($query) => $query->orWhere('feedback.id', $number)));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Feedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'category' => $feedback->category,
            'status' => $feedback->status,
            'status_changed_at' => $feedback->status_changed_at?->toIso8601String(),
            'message' => $feedback->message,
            'page_url' => $feedback->page_url,
            'user_agent' => $feedback->user_agent,
            'has_screenshot' => $feedback->screenshot_path !== null,
            'read' => $feedback->read_at !== null,
            'archived' => $feedback->archived_at !== null,
            'starred' => $feedback->starred_at !== null,
            'admin_note' => $feedback->admin_note,
            'labels' => $feedback->labels->pluck('id'),
            // This sender plus everyone marked "same as" this message
            'requests' => $feedback->duplicates_count + 1,
            'also_asked' => $feedback->duplicates->map(fn (Feedback $repeat) => [
                'id' => $repeat->id,
                'name' => $repeat->user?->name ?? 'Deleted account',
                'created_at' => $repeat->created_at->toIso8601String(),
            ]),
            'duplicate_of' => $feedback->duplicateOf ? [
                'id' => $feedback->duplicateOf->id,
                'message' => Str::limit(Str::squish($feedback->duplicateOf->message), 120),
                'user' => $feedback->duplicateOf->user?->name,
            ] : null,
            'created_at' => $feedback->created_at->toIso8601String(),
            'user' => $feedback->user ? [
                'id' => $feedback->user->id,
                'name' => $feedback->user->name,
                'email' => $feedback->user->email,
                'premium' => $feedback->user->isPremium(),
            ] : null,
        ];
    }
}
