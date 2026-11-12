<?php

namespace App\Http\Controllers;

use App\Enums\MeritLevel;
use App\Enums\MeritType;
use App\Models\Bloodline;
use App\Models\GoogleDriveConnection;
use App\Models\PedigreeImport;
use App\Models\Pigeon;
use App\Models\PigeonMerit;
use App\Services\ImageUploadService;
use App\Services\PedigreeImport\PedigreeDraft;
use App\Services\PedigreeImport\PedigreeImportLimits;
use App\Services\PedigreeImport\PedigreeImportRunner;
use App\Services\PedigreeImport\PedigreeImportSaver;
use App\Services\PedigreeImport\PedigreeReader;
use App\Services\PrivateFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Import pedigrees from photos, scans and PDFs of pedigree sheets, so a new user can bring their loft in without
 * typing every ancestor.
 *
 * Upload one sheet per request → it is read in the background (PedigreeImportRunner) → the user checks what was
 * read next to the sheet and saves it as a new pigeon, or into a pigeon already in the loft (PedigreeImportSaver).
 */
class PedigreeImportController extends Controller
{
    private const POSITIONS = ['S' => 'male', 'D' => 'female'];

    // Times a failed sheet can be read again before it has to be uploaded again (which counts as a new import)
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private PedigreeImportRunner $runner,
        private PedigreeImportLimits $limits,
        private PedigreeReader $reader,
        private PrivateFiles $files,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // The page polls while sheets are waiting, which keeps them being read
        $this->runner->kick($user->id);

        $imports = PedigreeImport::where('user_id', $user->id)
            ->where(fn ($query) => $query
                ->whereIn('status', PedigreeImport::OPEN)
                ->orWhere(fn ($saved) => $saved->where('status', PedigreeImport::SAVED)->where('updated_at', '>', now()->subDays(30))))
            ->with('pigeon:id,name,ring_number')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return Inertia::render('pedigree-imports/Index', [
            'imports' => $imports->map(fn (PedigreeImport $import) => $this->summary($import))->values(),
            'allowance' => $this->limits->allowance($user),
            'available' => $this->reader->configured(),
            'lastSaved' => $request->session()->get('pedigreeImportSaved'),
        ]);
    }

    /**
     * Upload one sheet. The browser sends sheets one at a time, so a big batch never runs into upload limits.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [
            'file.mimes' => 'Upload a photo (JPG, PNG or WebP) or a PDF of the pedigree.',
            'file.max' => 'Each file can be at most 10 MB.',
        ]);

        if (! $this->reader->configured()) {
            throw ValidationException::withMessages(['file' => 'Importing pedigrees is not available yet.']);
        }

        if ($refusal = $this->limits->refusal($user)) {
            throw ValidationException::withMessages(['file' => $refusal]);
        }

        // Re-encoded to WebP (dropping the photo's metadata, like its GPS location); big enough to read small print
        $stored = $this->files->store($request->file('file'), 'pedigree-imports', 2400);

        PedigreeImport::create([
            'user_id' => $user->id,
            'status' => PedigreeImport::PENDING,
            'file_path' => $stored['path'],
            'file_mime' => $stored['mime'],
            'file_name' => mb_substr($request->file('file')->getClientOriginalName() ?: 'Pedigree', 0, 255),
        ]);

        $this->runner->kick($user->id);

        return redirect()->route('pedigree-imports.index');
    }

    /**
     * Check a sheet that was read, next to the sheet itself.
     */
    public function show(Request $request, PedigreeImport $import): Response|RedirectResponse
    {
        $this->ensureOwner($request, $import);

        if ($import->status === PedigreeImport::SAVED && $import->pigeon_id) {
            return redirect()->route('pigeons.pedigree', $import->pigeon_id);
        }

        if ($import->status !== PedigreeImport::READY) {
            return redirect()->route('pedigree-imports.index');
        }

        $user = $request->user();
        $draft = $import->draft;
        $subject = PedigreeImportSaver::loftBirdByRing($user->id, $draft['bird']['ring_number'] ?? null);

        return Inertia::render('pedigree-imports/Review', [
            'sheet' => [
                'id' => $import->id,
                'file_name' => $import->file_name,
                'file_url' => $import->file_path ? route('pedigree-imports.file', $import) : null,
                'is_pdf' => $import->isPdf(),
                'note' => $draft['note'] ?? null,
            ],
            'draft' => $draft,
            // Birds on the sheet that are already in the loft: the bird itself, and parents that can be linked
            'matches' => [
                'bird' => $subject ? $this->matchSummary($subject, null) : null,
                ...collect(self::POSITIONS)->map(function (string $gender, string $position) use ($user, $draft) {
                    $match = PedigreeImportSaver::loftBirdByRing($user->id, $draft['ancestors'][$position]['ring_number'] ?? null);

                    return $match ? $this->matchSummary($match, $gender) : null;
                })->all(),
            ],
            'colors' => Pigeon::where('user_id', $user->id)->whereNotNull('color')->distinct()->orderBy('color')->pluck('color'),
            'bloodlines' => Bloodline::where('user_id', $user->id)->orderBy('name')->pluck('name'),
            'meritTypes' => MeritType::options(),
            'meritLevels' => MeritLevel::options(),
            'statuses' => collect(Pigeon::IN_LOFT_STATUSES)->map(fn (string $status) => ['value' => $status, 'label' => Pigeon::STATUS_LABELS[$status]])->values(),
            // The sheet can be kept with the bird's certificates, in the user's own Google Drive
            'canKeepSheet' => ! $import->isPdf() && GoogleDriveConnection::where('user_id', $user->id)->exists(),
            'waiting' => PedigreeImport::where('user_id', $user->id)->where('status', PedigreeImport::READY)->whereKeyNot($import->id)->count(),
            'lastSaved' => $request->session()->get('pedigreeImportSaved'),
        ]);
    }

    /**
     * The loft bird with a ring number (JSON), when a ring is changed on the review page.
     */
    public function match(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ring_number' => ['nullable', 'string', 'max:255'],
            'position' => ['required', Rule::in(['bird', ...array_keys(self::POSITIONS)])],
        ]);

        $match = PedigreeImportSaver::loftBirdByRing($request->user()->id, $validated['ring_number'] ?? null);

        return response()->json([
            'match' => $match ? $this->matchSummary($match, self::POSITIONS[$validated['position']] ?? null) : null,
        ]);
    }

    public function file(Request $request, PedigreeImport $import): HttpResponse
    {
        $this->ensureOwner($request, $import);
        abort_unless($import->file_path, 404);

        return $this->files->stream($import->file_path, $import->file_mime, $import->file_name ?: 'pedigree');
    }

    public function save(Request $request, PedigreeImport $import, PedigreeImportSaver $saver, ImageUploadService $images): RedirectResponse
    {
        $this->ensureOwner($request, $import);
        $user = $request->user();

        if ($import->status !== PedigreeImport::READY) {
            throw ValidationException::withMessages(['import' => 'This pedigree was already saved or removed.']);
        }

        $validated = $this->validateSave($request);
        $result = $saver->save($import, $validated);
        $pigeon = $result['pigeon'];

        $sheetKept = false;
        if ($request->boolean('keep_sheet') && ! $import->isPdf() && $import->file_path) {
            $sheetKept = $this->keepSheet($import, $pigeon, $images);
        }

        // What was read now lives in the records; the sheet and the reading aren't kept
        $this->files->delete($import->file_path);
        $import->update(['status' => PedigreeImport::SAVED, 'pigeon_id' => $pigeon->id, 'file_path' => null, 'draft' => null]);

        $next = PedigreeImport::where('user_id', $user->id)->where('status', PedigreeImport::READY)->orderBy('id')->first();

        return redirect()
            ->route($next ? 'pedigree-imports.show' : 'pedigree-imports.index', $next ? [$next] : [])
            ->with('pedigreeImportSaved', [
                'pigeon' => ['id' => $pigeon->id, 'label' => $pigeon->ring_number ?: $pigeon->name],
                'ancestors' => $result['ancestors'],
                'kept' => $result['kept'],
                'also_in' => $result['also_in'],
                'sheet_kept' => $sheetKept,
                'sheet_failed' => $request->boolean('keep_sheet') && ! $sheetKept && ! $import->isPdf(),
            ]);
    }

    /**
     * Read a sheet that failed again.
     */
    public function retry(Request $request, PedigreeImport $import): RedirectResponse
    {
        $this->ensureOwner($request, $import);

        if ($import->status !== PedigreeImport::FAILED || ! $import->file_path) {
            return redirect()->route('pedigree-imports.index');
        }

        if ($import->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['import' => 'This sheet could not be read several times. Remove it and try a clearer photo.']);
        }

        $import->update(['status' => PedigreeImport::PENDING, 'error' => null]);
        $this->runner->kick($import->user_id);

        return redirect()->route('pedigree-imports.index');
    }

    /**
     * Remove a sheet without saving it. The row stays, so it still counts toward the limits if it was read.
     */
    public function destroy(Request $request, PedigreeImport $import): RedirectResponse
    {
        $this->ensureOwner($request, $import);

        if ($import->isOpen()) {
            $this->files->delete($import->file_path);
            $import->update(['status' => PedigreeImport::DISCARDED, 'file_path' => null, 'draft' => null]);
        }

        return redirect()->route('pedigree-imports.index');
    }

    private function validateSave(Request $request): array
    {
        $ancestors = $request->input('ancestors', []);

        if (! is_array($ancestors) || count($ancestors) > 30) {
            throw ValidationException::withMessages(['ancestors' => 'Too many ancestors.']);
        }

        foreach (array_keys($ancestors) as $path) {
            if (! preg_match('/^[SD]{1,4}$/', (string) $path)) {
                throw ValidationException::withMessages(['ancestors' => 'An ancestor has an unknown place in the pedigree.']);
            }
        }

        $userId = $request->user()->id;

        $validated = $request->validate([
            'target' => ['required', Rule::in(['new', 'existing'])],
            'pigeon_id' => [
                'nullable',
                'required_if:target,existing',
                'integer',
                Rule::exists('pigeons', 'id')->where('user_id', $userId),
            ],
            'bird' => ['required', 'array'],
            'bird.ring_number' => ['nullable', 'required_if:target,new', 'string', 'max:255'],
            'bird.name' => ['nullable', 'string', 'max:255'],
            'bird.gender' => ['nullable', Rule::in(['male', 'female'])],
            'bird.color' => ['nullable', 'string', 'max:255'],
            'bird.bloodline' => ['nullable', 'string', 'max:255'],
            'bird.bred_by' => ['nullable', 'string', 'max:255'],
            'bird.remarks' => ['nullable', 'string', 'max:5000'],
            'bird.status' => ['nullable', Rule::in(Pigeon::IN_LOFT_STATUSES)],
            ...PigeonMerit::listRules('bird.merits'),
            'ancestors' => ['nullable', 'array'],
            'ancestors.*' => ['array'],
            'ancestors.*.ring_number' => ['nullable', 'required_without:ancestors.*.name', 'string', 'max:255'],
            'ancestors.*.name' => ['nullable', 'string', 'max:255'],
            'ancestors.*.color' => ['nullable', 'string', 'max:255'],
            'ancestors.*.bloodline' => ['nullable', 'string', 'max:255'],
            'ancestors.*.remarks' => ['nullable', 'string', 'max:2000'],
            ...PigeonMerit::listRules('ancestors.*.merits'),
            'links' => ['nullable', 'array'],
            ...collect(self::POSITIONS)->mapWithKeys(fn (string $gender, string $position) => ["links.{$position}" => [
                'nullable',
                'integer',
                Rule::exists('pigeons', 'id')->where('user_id', $userId)->where('gender', $gender),
            ]])->all(),
            'keep_sheet' => ['sometimes', 'boolean'],
        ], [
            'bird.ring_number.required_if' => 'Enter the ring number of the pigeon.',
            'ancestors.*.ring_number.required_without' => 'Each ancestor needs a ring number or a name.',
            'links.S.exists' => 'Link the sire to a cock in your loft.',
            'links.D.exists' => 'Link the dam to a hen in your loft.',
        ]);

        // Every ancestor needs the bird in front of it
        if (PedigreeDraft::unreachable($validated['ancestors'] ?? [])) {
            throw ValidationException::withMessages([
                'ancestors' => 'Some ancestors are behind an empty place in the pedigree. Fill in that place or remove them.',
            ]);
        }

        return $validated;
    }

    /**
     * Keep the sheet with the bird's certificates and relatives' photos, in the user's own Google Drive. A failure
     * doesn't undo the save: the pedigree itself is what matters.
     */
    private function keepSheet(PedigreeImport $import, Pigeon $pigeon, ImageUploadService $images): bool
    {
        $contents = $this->files->get($import->file_path);
        $temp = $contents !== null ? tempnam(sys_get_temp_dir(), 'pedigree') : false;

        if (! $temp) {
            return false;
        }

        try {
            file_put_contents($temp, $contents);
            $label = $pigeon->ring_number ?: $pigeon->name ?: 'Pigeon';
            $url = $images->upload(new UploadedFile($temp, 'pedigree.webp', 'image/webp', null, true), $import->user, "{$label} pedigree", 2400);
            $pigeon->update(['pedigree_images' => [...($pigeon->pedigree_images ?? []), $url]]);

            return true;
        } catch (Throwable $e) {
            Log::warning('Pedigree import: could not keep the sheet in Google Drive', ['import' => $import->id, 'message' => $e->getMessage()]);

            return false;
        } finally {
            @unlink($temp);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(PedigreeImport $import): array
    {
        $draft = $import->draft;

        return [
            'id' => $import->id,
            'status' => $import->status,
            'file_name' => $import->file_name,
            'file_url' => $import->file_path ? route('pedigree-imports.file', $import) : null,
            'is_pdf' => $import->isPdf(),
            'error' => $import->error,
            'can_retry' => $import->status === PedigreeImport::FAILED && $import->file_path && $import->attempts < self::MAX_ATTEMPTS,
            'bird' => $draft ? [
                'ring_number' => $draft['bird']['ring_number'] ?? null,
                'name' => $draft['bird']['name'] ?? null,
            ] : null,
            'ancestors' => $draft ? count($draft['ancestors'] ?? []) : 0,
            'note' => $draft['note'] ?? null,
            'pigeon' => $import->pigeon ? [
                'id' => $import->pigeon->id,
                'ring_number' => $import->pigeon->ring_number,
                'name' => $import->pigeon->name,
            ] : null,
        ];
    }

    /**
     * A loft bird found by ring number, in the standard search result format, and whether it can be linked as the
     * parent in that place (a sire must be a cock and a dam a hen).
     */
    private function matchSummary(Pigeon $pigeon, ?string $gender): array
    {
        $pigeon->load(Pigeon::SEARCH_RELATIONS);

        return [
            ...$pigeon->searchSummary(),
            'linkable' => $gender === null || $pigeon->gender === $gender,
        ];
    }

    private function ensureOwner(Request $request, PedigreeImport $import): void
    {
        if ($import->user_id !== $request->user()?->id) {
            abort(404);
        }
    }
}
