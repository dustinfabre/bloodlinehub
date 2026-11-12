<?php

namespace App\Services\PedigreeImport;

use App\Models\PedigreeImport;
use App\Services\PrivateFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Reads uploaded pedigree sheets in the background, without a queue worker.
 *
 * kick() starts a run right after the response (Laravel's defer), unless enough sheets of that user are already
 * being read. A run claims the user's waiting sheets one at a time until none are left or its time is up; the
 * import page polls while sheets are waiting and kicks again, so reading carries on as long as the page is open.
 * A sheet left "reading" by a run that died (a server restart, a time limit) is put back in the queue once, then
 * marked failed.
 */
class PedigreeImportRunner
{
    // A sheet takes well under a minute to read; one still "reading" after this was abandoned
    private const STALE_AFTER_MINUTES = 5;

    private const MAX_ATTEMPTS = 2;

    // A run stops claiming new sheets after this long, so it doesn't hold a server process for ages
    private const RUN_SECONDS = 300;

    public function __construct(
        private PedigreeReader $reader,
        private PrivateFiles $files,
    ) {}

    public function kick(int $userId): void
    {
        $this->recoverStale($userId);

        $counts = PedigreeImport::where('user_id', $userId)
            ->whereIn('status', [PedigreeImport::PENDING, PedigreeImport::READING])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        if (! ($counts[PedigreeImport::PENDING] ?? 0) || ($counts[PedigreeImport::READING] ?? 0) >= max(1, config('pedigree_import.parallel'))) {
            return;
        }

        defer(fn () => $this->run($userId));
    }

    /**
     * Read the user's waiting sheets, oldest first.
     */
    public function run(int $userId): void
    {
        $until = time() + self::RUN_SECONDS;

        while (time() < $until && ($import = $this->claim($userId))) {
            @set_time_limit(300);
            $this->read($import);
        }
    }

    public function read(PedigreeImport $import): void
    {
        $outcome = $this->outcome($import);

        // The user may have removed the sheet while it was being read
        if (PedigreeImport::whereKey($import->id)->value('status') === PedigreeImport::READING) {
            $import->update($outcome);
        }
    }

    /**
     * What reading the sheet gave: the draft to check, or why it failed.
     *
     * @return array<string, mixed> attributes to save on the import
     */
    private function outcome(PedigreeImport $import): array
    {
        $contents = $import->file_path ? $this->files->get($import->file_path) : null;

        if ($contents === null) {
            return ['status' => PedigreeImport::FAILED, 'error' => 'The uploaded file is missing. Remove it and upload it again.'];
        }

        try {
            $reading = $this->reader->read($contents, $import->file_mime);
        } catch (PedigreeReadFailed $e) {
            return ['status' => PedigreeImport::FAILED, 'error' => $e->getMessage(), 'usage' => $e->usage];
        } catch (Throwable $e) {
            report($e);

            return ['status' => PedigreeImport::FAILED, 'error' => 'Something went wrong while reading this sheet. Try again.'];
        }

        $draft = PedigreeDraft::fromReading($reading['result']);

        if (! $draft) {
            $note = is_string($reading['result']['note'] ?? null) ? trim($reading['result']['note']) : '';

            return [
                'status' => PedigreeImport::FAILED,
                'error' => mb_substr($note !== '' ? $note : 'No pigeon pedigree was found in this file.', 0, 500),
                'usage' => $reading['usage'],
            ];
        }

        Log::info('Pedigree import read', ['import' => $import->id, 'ancestors' => count($draft['ancestors']), ...$reading['usage']]);

        return ['status' => PedigreeImport::READY, 'draft' => $draft, 'error' => null, 'usage' => $reading['usage']];
    }

    /**
     * Take the user's oldest waiting sheet, so no other run reads it too.
     */
    private function claim(int $userId): ?PedigreeImport
    {
        for ($try = 0; $try < 3; $try++) {
            $id = PedigreeImport::where('user_id', $userId)
                ->where('status', PedigreeImport::PENDING)
                ->orderBy('id')
                ->value('id');

            if (! $id) {
                return null;
            }

            $claimed = PedigreeImport::whereKey($id)
                ->where('status', PedigreeImport::PENDING)
                ->update([
                    'status' => PedigreeImport::READING,
                    'started_at' => now(),
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => now(),
                ]);

            if ($claimed) {
                return PedigreeImport::find($id);
            }
        }

        return null;
    }

    private function recoverStale(int $userId): void
    {
        $stale = PedigreeImport::where('user_id', $userId)
            ->where('status', PedigreeImport::READING)
            ->where('started_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES));

        (clone $stale)->where('attempts', '<', self::MAX_ATTEMPTS)->update(['status' => PedigreeImport::PENDING]);
        $stale->update(['status' => PedigreeImport::FAILED, 'error' => 'Reading this sheet took too long. Try again.']);
    }
}
