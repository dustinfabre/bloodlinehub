<?php

namespace App\Console\Commands;

use App\Services\PedigreeImport\PedigreeDraft;
use App\Services\PedigreeImport\PedigreeReader;
use App\Services\PedigreeImport\PedigreeReadFailed;
use Illuminate\Console\Command;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Reads a pedigree sheet the way the import page does and prints what was read, without saving anything. For
 * trying sample sheets and tuning the reader (model, effort, instructions).
 */
class ReadPedigree extends Command
{
    protected $signature = 'pedigree-imports:read
        {file : Path to a photo, scan or PDF of a pedigree}
        {--raw : Print the reader\'s answer as it came, before it is cleaned}';

    protected $description = 'Read a pedigree sheet with the import reader and print the result (nothing is saved)';

    public function handle(PedigreeReader $reader): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Can't read {$path}.");

            return self::FAILURE;
        }

        if (! $reader->configured()) {
            $this->error('Set ANTHROPIC_API_KEY first.');

            return self::FAILURE;
        }

        // Prepared like an upload: images become WebP at most 2400 px on the long side
        if (mime_content_type($path) === 'application/pdf') {
            [$contents, $mime] = [file_get_contents($path), 'application/pdf'];
        } else {
            $image = Image::read($path);
            if (max($image->width(), $image->height()) > 2400) {
                $image->scaleDown(width: 2400, height: 2400);
            }
            [$contents, $mime] = [(string) $image->toWebp(85), 'image/webp'];
        }

        $started = microtime(true);

        try {
            $reading = $reader->read($contents, $mime);
        } catch (PedigreeReadFailed $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $result = $this->option('raw') ? $reading['result'] : PedigreeDraft::fromReading($reading['result']);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info(sprintf(
            '%s · %d input + %d output tokens · %.1f s',
            $reading['usage']['model'],
            $reading['usage']['input_tokens'],
            $reading['usage']['output_tokens'],
            microtime(true) - $started,
        ));

        return self::SUCCESS;
    }
}
