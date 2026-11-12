<?php

namespace App\Services\PedigreeImport;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Enums\MeritLevel;
use App\Enums\MeritType;
use Illuminate\Support\Facades\Log;

/**
 * Reads a pedigree sheet (photo, scan or PDF) with Claude: every bird on it, by its place in the pedigree.
 *
 * The answer is constrained to a JSON schema (structured outputs), so it always parses. What it says still has to be
 * checked: PedigreeDraft cleans it into the review form's shape, and the user checks it before anything is saved.
 */
class PedigreeReader
{
    // Paths from the bird: "S" its sire, "SD" its sire's dam, ... down to the great-great-grandparents
    private const MAX_DEPTH = 4;

    public function configured(): bool
    {
        return filled(config('pedigree_import.api_key'));
    }

    /**
     * @return array{result: array<string, mixed>, usage: array<string, mixed>}
     *
     * @throws PedigreeReadFailed
     */
    public function read(string $contents, string $mime): array
    {
        if (! $this->configured()) {
            throw new PedigreeReadFailed('Importing pedigrees is not set up yet.');
        }

        $source = ['type' => 'base64', 'mediaType' => $mime, 'data' => base64_encode($contents)];
        $file = $mime === 'application/pdf'
            ? ['type' => 'document', 'source' => $source]
            : ['type' => 'image', 'source' => $source];

        try {
            $message = $this->client()->beta->messages->create(
                model: config('pedigree_import.model'),
                maxTokens: 16000,
                system: self::instructions(),
                messages: [[
                    'role' => 'user',
                    'content' => [$file, ['type' => 'text', 'text' => 'Read this pedigree.']],
                ]],
                outputConfig: [
                    'effort' => config('pedigree_import.effort'),
                    'format' => ['type' => 'json_schema', 'schema' => self::schema()],
                ],
                // If the model declines (it shouldn't for a pedigree), the API retries on its default fallback
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default',
            );
        } catch (RateLimitException|InternalServerException|APIConnectionException $e) {
            Log::warning('Pedigree import: the reader is unavailable', ['message' => $e->getMessage()]);

            throw new PedigreeReadFailed('The pedigree reader is busy right now. Try again in a minute.');
        } catch (APIStatusException $e) {
            Log::error('Pedigree import: the request was refused', ['message' => $e->getMessage()]);

            throw new PedigreeReadFailed('Importing pedigrees is not working right now. We have been notified.');
        }

        $usage = [
            'model' => $message->model,
            'input_tokens' => $message->usage->inputTokens,
            'output_tokens' => $message->usage->outputTokens,
        ];

        if ($message->stopReason === 'refusal') {
            throw new PedigreeReadFailed('This file could not be read. Try a clearer photo of the pedigree.', $usage);
        }

        if ($message->stopReason === 'max_tokens') {
            throw new PedigreeReadFailed('This sheet has more text than we can read in one go. Try a photo of a single pedigree.', $usage);
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $result = json_decode($text, true);

        if (! is_array($result)) {
            Log::warning('Pedigree import: the answer was not JSON', ['stop_reason' => $message->stopReason]);

            throw new PedigreeReadFailed('This file could not be read. Try again, or try a clearer photo.', $usage);
        }

        return ['result' => $result, 'usage' => $usage];
    }

    protected function client(): Client
    {
        return new Client(
            apiKey: config('pedigree_import.api_key'),
            requestOptions: ['timeout' => 240.0, 'maxRetries' => 2],
        );
    }

    /**
     * Every place in the pedigree, as the reader names it: "bird" for the bird itself, then its ancestors by path.
     *
     * @return list<string>
     */
    public static function positions(): array
    {
        $positions = ['bird'];
        $generation = [''];

        for ($depth = 1; $depth <= self::MAX_DEPTH; $depth++) {
            $generation = array_merge(...array_map(fn (string $path) => [$path.'S', $path.'D'], $generation));
            array_push($positions, ...$generation);
        }

        return $positions;
    }

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $text = fn (string $description) => [
            'description' => $description,
            'anyOf' => [['type' => 'string'], ['type' => 'null']],
        ];

        $merit = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['type', 'level', 'year', 'detail'],
            'properties' => [
                'type' => ['type' => 'string', 'enum' => array_map(fn (MeritType $type) => $type->value, MeritType::cases())],
                'level' => [
                    'anyOf' => [
                        ['type' => 'string', 'enum' => array_map(fn (MeritLevel $level) => $level->value, MeritLevel::cases())],
                        ['type' => 'null'],
                    ],
                ],
                'year' => ['anyOf' => [['type' => 'integer'], ['type' => 'null']]],
                'detail' => $text('The result in a few words, e.g. "1st Final Rimini OLR 503 km, 1027 birds"'),
            ],
        ];

        $bird = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['position', 'ring_number', 'name', 'color', 'strain', 'breeder', 'remarks', 'merits'],
            'properties' => [
                'position' => ['type' => 'string', 'enum' => self::positions()],
                'ring_number' => $text('The ring number exactly as printed, with its country code and year'),
                'name' => $text("The bird's own name or nickname, if it has one"),
                'color' => $text('Plumage color'),
                'strain' => $text('Strain or bloodline, only when the sheet names one for this bird'),
                'breeder' => $text('The breeder, loft or fancier named for this bird'),
                'remarks' => $text('Everything else written for this bird, one fact per line'),
                'merits' => ['type' => 'array', 'items' => $merit],
            ],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['is_pedigree', 'note', 'sex', 'birds'],
            'properties' => [
                'is_pedigree' => ['type' => 'boolean'],
                'note' => $text('A short note for the user about anything that could not be read, or null'),
                'sex' => ['anyOf' => [['type' => 'string', 'enum' => ['male', 'female']], ['type' => 'null']]],
                'birds' => ['type' => 'array', 'items' => $bird],
            ],
        ];
    }

    public static function instructions(): string
    {
        $merits = implode("\n", array_map(
            fn (MeritType $type) => "- {$type->value}: {$type->description()}",
            MeritType::cases(),
        ));
        $levels = implode(', ', array_map(fn (MeritLevel $level) => "{$level->value} ({$level->label()})", MeritLevel::cases()));

        return <<<PROMPT
        You read racing pigeon pedigree sheets for BloodlineHub, an app where pigeon fanciers keep their loft records. Fanciers upload a photo, scan or PDF of a pedigree, you read every bird on it, and they check your reading before it is saved, so read faithfully and leave out anything you cannot make out rather than guessing.

        ## Positions
        A pedigree shows one bird (position "bird") and its ancestors. Name each ancestor by the path from the bird: "S" is its sire (father), "D" its dam (mother), "SS" the sire's sire, "SD" the sire's dam, "DS" the dam's sire, and so on, up to four letters (the great-great-grandparents).

        Work out positions from the layout and the connecting lines, not from where a box happens to sit on the page. On almost every sheet the sire's side is the upper half and the dam's side the lower half, and in each pair of parents the sire is above the dam. Some sheets number the boxes: 1 sire, 2 dam, 3 to 6 the grandparents and 7 to 14 the great-grandparents, in the same top-to-bottom order. Sex markers differ between languages (M is male in English but Mutter, the dam, in German; V is Vater in German but vrouw, a hen, in Dutch), so trust the layout first. Leave out empty boxes.

        If the file holds more than one pedigree, read the first and say so in the note. If it is not a pigeon pedigree, or is too blurry or cut off to read, set is_pedigree to false, explain why in the note in one short sentence, and return no birds.

        ## Each bird
        - ring_number: as printed, keeping the order of its parts and its country code, e.g. "BE 24-3084954", "NL 16-1596240", "DV-04683-19-0321". Read the digits carefully; they are how birds are matched across pedigrees.
        - name: the bird's own name or nickname ("KING IMBREA", "Blue Swan"), never the breeder's name. Leave out quotation marks.
        - color: the plumage color. Translate it to English when it is written in another language (Blåbåndet is Blue Bar) and keep common abbreviations as printed (BB, BC, RC).
        - sex: the sex of the bird itself (position "bird") when the sheet shows it; ancestors' sexes follow from their positions.
        - strain: only when the sheet names a strain or bloodline for that bird, for example after "Strain", "Stamme" or "Abstammung", or in the title of the main bird.
        - breeder: the breeder, loft or fancier named in the bird's box, such as "GANUS FAMILY LOFT" or "Gaby Vandenabeele". For the main bird, the loft that issued the pedigree when the sheet presents it as the breeder.
        - remarks: the rest of the text in the box, in its original wording: titles, race results, relatives ("Full brother of ..."), what the bird bred. Put each fact on its own line and join words that were only split by the edge of the box. Do not repeat the ring number, name, color, strain or breeder.
        - Never copy phone numbers, email addresses, street addresses or website links anywhere: they are personal details of other people.

        ## Merits
        Also list the merits each bird's own results earn, using these types:
        {$merits}

        Levels: {$levels}. Use derby or olr for one loft races, and the year the merit was earned when it is printed. Only add a merit the text states plainly for that bird; results of its relatives or offspring are not its own merits, except that super_breeder, breeder_of_winners and foundation describe what it bred. When in doubt, leave the merit out: the remarks keep the full text anyway.
        PROMPT;
    }
}
