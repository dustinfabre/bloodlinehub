<?php

namespace Tests\Feature;

use App\Models\PedigreeImport;
use App\Models\Pigeon;
use App\Models\User;
use App\Services\PedigreeImport\PedigreeDraft;
use App\Services\PedigreeImport\PedigreeImportRunner;
use App\Services\PedigreeImport\PedigreeReader;
use App\Services\PedigreeImport\PedigreeReadFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PedigreeImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    /** What the fake reader answers next */
    private array|PedigreeReadFailed $answer;

    private int $reads = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Deferred work (reading sheets after the response) runs straight away in tests
        $this->withoutDefer();
        Storage::fake('r2');
        config(['pedigree_import.api_key' => 'test-key', 'pedigree_import.free_limit' => 3]);

        $this->user = User::factory()->create();
        $this->answer = self::reading();

        $test = $this;
        $this->app->instance(PedigreeReader::class, new class($test) extends PedigreeReader
        {
            public function __construct(private PedigreeImportTest $test) {}

            public function read(string $contents, string $mime): array
            {
                return $this->test->fakeRead();
            }
        });
    }

    public function fakeRead(): array
    {
        $this->reads++;

        if ($this->answer instanceof PedigreeReadFailed) {
            throw $this->answer;
        }

        return ['result' => $this->answer, 'usage' => ['model' => 'test', 'input_tokens' => 2000, 'output_tokens' => 900]];
    }

    /**
     * A reading of a sheet like the AE 24-2205410 sample: the bird, both parents, grandparents and a few more.
     */
    private static function reading(array $overrides = []): array
    {
        $bird = fn (string $position, ?string $ring, ?string $name = null, array $extra = []) => [
            'position' => $position,
            'ring_number' => $ring,
            'name' => $name,
            'color' => null,
            'strain' => null,
            'breeder' => null,
            'remarks' => null,
            'merits' => [],
            ...$extra,
        ];

        return [
            'is_pedigree' => true,
            'note' => null,
            'sex' => 'male',
            'birds' => [
                $bird('bird', 'ae 24-2205410', null, ['color' => 'BLUE', 'breeder' => 'ALMANSOORI TEAM']),
                $bird('S', 'KT-23-55275', 'THE KING OF OLR', [
                    'breeder' => 'BADER AL SANAFI',
                    'remarks' => "Blauw full bro sis\n88 330 km hot spot",
                    'merits' => [['type' => 'hot_spot_winner', 'level' => 'olr', 'year' => 2023, 'detail' => 'Hot Spot Pattaya OLR']],
                ]),
                $bird('D', 'AE 22-100580', null, ['color' => 'CHEQUER']),
                $bird('SS', 'GFL-20-83', 'FULL BROTHER LAURA', ['breeder' => 'GANUS FAMILY LOFT']),
                $bird('SD', 'GFL-20-226', 'HALOVER MAGIC'),
                $bird('DS', '07274-21-265'),
                $bird('DD', 'AE 21-1403225'),
                $bird('SSS', 'DV-10-85', 'SUN CITY'),
                $bird('SSSS', 'DV-04-683', 'CRACK DOFFER'),
                ...$overrides,
            ],
        ];
    }

    private function upload(?User $user = null)
    {
        return $this->actingAs($user ?? $this->user)->post(route('pedigree-imports.store'), [
            'file' => UploadedFile::fake()->image('pedigree.jpg', 1200, 1600),
        ]);
    }

    private function readyImport(): PedigreeImport
    {
        $this->upload()->assertRedirect(route('pedigree-imports.index'));

        $import = PedigreeImport::where('user_id', $this->user->id)->latest('id')->firstOrFail();
        $this->assertSame(PedigreeImport::READY, $import->status);

        return $import;
    }

    /**
     * The review form as the page would send it back, unchanged.
     */
    private function form(PedigreeImport $import, array $overrides = []): array
    {
        return [
            'target' => 'new',
            'pigeon_id' => null,
            'bird' => $import->draft['bird'],
            'ancestors' => $import->draft['ancestors'],
            'links' => [],
            ...$overrides,
        ];
    }

    private function pigeon(array $attributes = [], ?User $owner = null): Pigeon
    {
        return Pigeon::create([
            'user_id' => ($owner ?? $this->user)->id,
            'status' => 'stock',
            'ring_number' => 'BE-2024-'.rand(100000, 999999),
            ...$attributes,
        ]);
    }

    public function test_an_uploaded_sheet_is_read_into_a_draft(): void
    {
        $import = $this->readyImport();

        Storage::disk('r2')->assertExists($import->file_path);
        $this->assertSame('image/webp', $import->file_mime);
        $this->assertSame(2000, $import->usage['input_tokens']);

        $draft = $import->draft;
        $this->assertSame('AE 24-2205410', $draft['bird']['ring_number']);
        $this->assertSame('male', $draft['bird']['gender']);
        $this->assertSame('ALMANSOORI TEAM', $draft['bird']['bred_by']);
        // An ancestor's breeder heads its remarks
        $this->assertSame("BADER AL SANAFI\nBlauw full bro sis\n88 330 km hot spot", $draft['ancestors']['S']['remarks']);
        $this->assertSame('hot_spot_winner', $draft['ancestors']['S']['merits'][0]['type']);
        // Parents before grandparents, so the bird in front of each ancestor comes first
        $this->assertSame(['D', 'S', 'DD', 'DS', 'SD', 'SS', 'SSS', 'SSSS'], array_keys($draft['ancestors']));

        $this->actingAs($this->user)->get(route('pedigree-imports.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pedigree-imports/Index')
                ->where('imports.0.status', 'ready')
                ->where('imports.0.bird.ring_number', 'AE 24-2205410')
                ->where('imports.0.ancestors', 8)
                ->where('allowance.free_left', 2));
    }

    public function test_the_draft_leaves_out_what_cannot_be_used(): void
    {
        $draft = PedigreeDraft::fromReading(self::reading([
            ['position' => 'S', 'ring_number' => 'DUPLICATE', 'name' => null, 'color' => null, 'strain' => null, 'breeder' => null, 'remarks' => null, 'merits' => []],
            ['position' => 'SSSSS', 'ring_number' => 'TOO-DEEP', 'name' => null, 'color' => null, 'strain' => null, 'breeder' => null, 'remarks' => null, 'merits' => []],
            ['position' => 'DDS', 'ring_number' => null, 'name' => null, 'color' => 'BLUE', 'strain' => null, 'breeder' => null, 'remarks' => 'No ring or name', 'merits' => []],
            ['position' => 'DSS', 'ring_number' => '  nl 18-1456017 ', 'name' => '"MELLE"', 'color' => null, 'strain' => null, 'breeder' => null, 'remarks' => null, 'merits' => [
                ['type' => 'not_a_merit', 'level' => null, 'year' => null, 'detail' => null],
                ['type' => 'ace_pigeon', 'level' => 'galaxy', 'year' => 1800, 'detail' => '1. Ace Pigeon'],
            ]],
        ]));

        $this->assertSame('KT-23-55275', $draft['ancestors']['S']['ring_number']);
        $this->assertArrayNotHasKey('SSSSS', $draft['ancestors']);
        $this->assertArrayNotHasKey('DDS', $draft['ancestors']);
        $this->assertSame('NL 18-1456017', $draft['ancestors']['DSS']['ring_number']);
        $this->assertSame('MELLE', $draft['ancestors']['DSS']['name']);
        $this->assertSame([['type' => 'ace_pigeon', 'level' => null, 'year' => null, 'detail' => '1. Ace Pigeon']], $draft['ancestors']['DSS']['merits']);

        $this->assertNull(PedigreeDraft::fromReading(['is_pedigree' => false, 'note' => 'A photo of a cat', 'sex' => null, 'birds' => []]));
        $this->assertSame(['SDS'], PedigreeDraft::unreachable(['S' => [], 'SDS' => [], 'D' => []]));
    }

    public function test_saving_creates_the_pigeon_and_its_ancestors(): void
    {
        $import = $this->readyImport();

        $this->post(route('pedigree-imports.save', $import), $this->form($import, [
            'bird' => [...$import->draft['bird'], 'name' => 'blue boy', 'bloodline' => 'Ganus', 'status' => 'racing'],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('pedigree-imports.index'));

        $pigeon = Pigeon::where('ring_number', 'AE 24-2205410')->firstOrFail();
        $this->assertSame('BLUE BOY', $pigeon->name);
        $this->assertSame('racing', $pigeon->status);
        $this->assertSame('ALMANSOORI TEAM', $pigeon->bred_by);
        $this->assertSame('GANUS', $pigeon->bloodline);
        $this->assertSame('GANUS', $pigeon->primaryBloodline->name);

        $this->assertSame('KT-23-55275', $pigeon->sire_ring_number);
        $this->assertSame('THE KING OF OLR', $pigeon->sire_name);
        $this->assertSame('hot_spot_winner', $pigeon->sire_merits[0]['type']);
        $this->assertSame('AE 22-100580', $pigeon->dam_ring_number);
        $this->assertSame(
            ['DD', 'DS', 'SD', 'SS', 'SSS', 'SSSS'],
            $pigeon->pedigreeAncestors()->orderBy('path')->pluck('path')->all(),
        );

        $import->refresh();
        $this->assertSame(PedigreeImport::SAVED, $import->status);
        $this->assertSame($pigeon->id, $import->pigeon_id);
        $this->assertNull($import->file_path);
        $this->assertSame([], Storage::disk('r2')->allFiles());
    }

    public function test_merits_on_the_sheet_are_added_to_the_bird_itself(): void
    {
        $import = $this->readyImport();
        $merit = ['type' => 'ace_pigeon', 'level' => 'national', 'year' => 2025, 'detail' => '1st Ace Bulgaria'];

        $this->post(route('pedigree-imports.save', $import), $this->form($import, [
            'bird' => [...$import->draft['bird'], 'merits' => [$merit, $merit]],
        ]))->assertSessionHasNoErrors();

        $pigeon = Pigeon::where('ring_number', 'AE 24-2205410')->firstOrFail();
        $this->assertSame(1, $pigeon->merits()->count());
        $this->assertNull($pigeon->merits()->first()->source);
    }

    public function test_a_parent_in_the_loft_is_linked_and_its_own_pedigree_filled_in(): void
    {
        $sire = $this->pigeon(['ring_number' => 'KT-23-55275', 'gender' => 'male', 'name' => 'KING']);
        // Already known on the sire: a different dam, which must stay
        $sire->update(['dam_ring_number' => 'XX-99-1']);
        $import = $this->readyImport();

        $this->actingAs($this->user)->get(route('pedigree-imports.show', $import))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pedigree-imports/Review')
                ->where('matches.S.id', $sire->id)
                ->where('matches.S.linkable', true)
                ->where('matches.D', null)
                ->where('matches.bird', null));

        $this->post(route('pedigree-imports.save', $import), $this->form($import, ['links' => ['S' => $sire->id]]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('pedigreeImportSaved.kept', ['SD']);

        $pigeon = Pigeon::where('ring_number', 'AE 24-2205410')->firstOrFail();
        $this->assertSame($sire->id, $pigeon->sire_id);
        $this->assertSame('KING', $pigeon->sire_name);
        // Nothing is stored behind the linked sire on the new bird itself: it comes from the sire's own record
        $this->assertSame(['DD', 'DS'], $pigeon->pedigreeAncestors()->orderBy('path')->pluck('path')->all());

        $sire->refresh();
        $this->assertSame('GFL-20-83', $sire->sire_ring_number);
        $this->assertSame('XX-99-1', $sire->dam_ring_number);
        $this->assertSame(['SS', 'SSS'], $sire->pedigreeAncestors()->orderBy('path')->pluck('path')->all());
    }

    public function test_a_parent_of_the_wrong_sex_is_not_offered_for_linking(): void
    {
        $this->pigeon(['ring_number' => 'KT 23 55275', 'gender' => 'female']);
        $hen = Pigeon::where('ring_number', 'KT 23 55275')->first();
        $import = $this->readyImport();

        $this->actingAs($this->user)->get(route('pedigree-imports.show', $import))
            ->assertInertia(fn (Assert $page) => $page->where('matches.S.linkable', false));

        $this->post(route('pedigree-imports.save', $import), $this->form($import, ['links' => ['S' => $hen->id]]))
            ->assertSessionHasErrors('links.S');
    }

    public function test_a_new_pigeon_cannot_repeat_a_ring_already_in_the_loft(): void
    {
        $existing = $this->pigeon(['ring_number' => 'AE-24-2205410']);
        $import = $this->readyImport();

        $this->actingAs($this->user)->get(route('pedigree-imports.show', $import))
            ->assertInertia(fn (Assert $page) => $page->where('matches.bird.id', $existing->id));

        $this->post(route('pedigree-imports.save', $import), $this->form($import))
            ->assertSessionHasErrors('bird.ring_number');

        $this->assertSame(1, Pigeon::count());
        $this->assertSame(PedigreeImport::READY, $import->fresh()->status);
    }

    public function test_saving_into_an_existing_pigeon_only_fills_what_is_missing(): void
    {
        $existing = $this->pigeon([
            'ring_number' => 'AE-24-2205410',
            'color' => 'BLUE BAR',
            'sire_ring_number' => 'KT 23 55275',
            'dam_ring_number' => 'SOME-OTHER-DAM',
            'dam_name' => 'OTHER',
        ]);
        $import = $this->readyImport();

        $this->post(route('pedigree-imports.save', $import), $this->form($import, [
            'target' => 'existing',
            'pigeon_id' => $existing->id,
        ]))->assertSessionHasNoErrors()->assertSessionHas('pedigreeImportSaved.kept', ['D']);

        $existing->refresh();
        $this->assertSame('BLUE BAR', $existing->color);
        $this->assertSame('male', $existing->gender);
        $this->assertSame('ALMANSOORI TEAM', $existing->bred_by);
        // The same sire (ring written differently) gets the name it lacked; the different dam is kept as it was
        $this->assertSame('KT 23 55275', $existing->sire_ring_number);
        $this->assertSame('THE KING OF OLR', $existing->sire_name);
        $this->assertSame('SOME-OTHER-DAM', $existing->dam_ring_number);
        $this->assertSame(['SD', 'SS', 'SSS', 'SSSS'], $existing->pedigreeAncestors()->orderBy('path')->pluck('path')->all());
        $this->assertSame(1, Pigeon::count());
    }

    public function test_other_pedigrees_with_the_same_outside_bird_gain_its_ancestors(): void
    {
        // A half-brother already in the loft, with the same outside sire and nothing behind it
        $halfBrother = $this->pigeon(['sire_ring_number' => 'KT-23-55275']);
        $import = $this->readyImport();

        $this->post(route('pedigree-imports.save', $import), $this->form($import))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('pedigreeImportSaved.also_in', [$halfBrother->ring_number]);

        $this->assertSame(
            ['SD', 'SS', 'SSS', 'SSSS'],
            $halfBrother->pedigreeAncestors()->orderBy('path')->pluck('path')->all(),
        );
    }

    public function test_ancestors_behind_an_empty_place_are_refused(): void
    {
        $import = $this->readyImport();
        $ancestors = $import->draft['ancestors'];
        unset($ancestors['SS']);

        $this->post(route('pedigree-imports.save', $import), $this->form($import, ['ancestors' => $ancestors]))
            ->assertSessionHasErrors('ancestors');
    }

    public function test_after_saving_the_next_sheet_to_check_opens(): void
    {
        $first = $this->readyImport();
        $second = $this->readyImport();

        $this->post(route('pedigree-imports.save', $first), $this->form($first))
            ->assertRedirect(route('pedigree-imports.show', $second));
    }

    public function test_an_unreadable_sheet_fails_with_the_readers_note_and_can_be_retried(): void
    {
        $this->answer = ['is_pedigree' => false, 'note' => 'This is a photo of a loft, not a pedigree.', 'sex' => null, 'birds' => []];
        $this->upload();

        $import = PedigreeImport::firstOrFail();
        $this->assertSame(PedigreeImport::FAILED, $import->status);
        $this->assertSame('This is a photo of a loft, not a pedigree.', $import->error);

        $this->answer = self::reading();
        $this->post(route('pedigree-imports.retry', $import))->assertRedirect(route('pedigree-imports.index'));

        $this->assertSame(PedigreeImport::READY, $import->fresh()->status);
        $this->assertSame(2, $this->reads);
    }

    public function test_a_reader_error_is_shown_and_does_not_count_toward_the_limit(): void
    {
        $this->answer = new PedigreeReadFailed('The pedigree reader is busy right now. Try again in a minute.');

        foreach (range(1, 4) as $ignored) {
            $this->upload()->assertSessionHasNoErrors();
        }

        $this->assertSame(4, PedigreeImport::where('status', PedigreeImport::FAILED)->count());
        $this->assertSame('The pedigree reader is busy right now. Try again in a minute.', PedigreeImport::first()->error);
    }

    public function test_free_accounts_can_import_a_limited_number_of_sheets(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->upload()->assertSessionHasNoErrors();
        }

        $this->upload()->assertSessionHasErrors('file');
        $this->assertSame(3, PedigreeImport::count());

        // Removing a sheet that was read doesn't give the import back
        $this->delete(route('pedigree-imports.destroy', PedigreeImport::first()));
        $this->upload()->assertSessionHasErrors('file');

        $premium = User::factory()->premium()->create();
        foreach (range(1, 4) as $ignored) {
            $this->upload($premium)->assertSessionHasNoErrors();
        }
    }

    public function test_the_daily_limit_applies_to_everyone(): void
    {
        config(['pedigree_import.daily_limit' => 2]);
        $premium = User::factory()->premium()->create();

        $this->upload($premium)->assertSessionHasNoErrors();
        $this->upload($premium)->assertSessionHasNoErrors();
        $this->upload($premium)->assertSessionHasErrors('file');
    }

    public function test_importing_is_unavailable_without_an_api_key(): void
    {
        config(['pedigree_import.api_key' => null]);

        $this->upload()->assertSessionHasErrors('file');
        $this->assertSame(0, PedigreeImport::count());

        $this->get(route('pedigree-imports.index'))
            ->assertInertia(fn (Assert $page) => $page->where('available', false));
    }

    public function test_only_files_of_a_pedigree_can_be_uploaded(): void
    {
        $this->actingAs($this->user)->post(route('pedigree-imports.store'), [
            'file' => UploadedFile::fake()->create('notes.docx', 20),
        ])->assertSessionHasErrors('file');
    }

    public function test_imports_are_private_to_their_owner(): void
    {
        $import = $this->readyImport();
        $other = User::factory()->premium()->create();

        $this->actingAs($other)->get(route('pedigree-imports.show', $import))->assertNotFound();
        $this->actingAs($other)->get(route('pedigree-imports.file', $import))->assertNotFound();
        $this->actingAs($other)->post(route('pedigree-imports.save', $import), $this->form($import))->assertNotFound();
        $this->actingAs($other)->delete(route('pedigree-imports.destroy', $import))->assertNotFound();
        $this->actingAs($other)->get(route('pedigree-imports.index'))
            ->assertInertia(fn (Assert $page) => $page->where('imports', []));

        $this->assertSame(PedigreeImport::READY, $import->fresh()->status);
    }

    public function test_a_sheet_removed_while_being_read_stays_removed(): void
    {
        $this->upload();
        $import = PedigreeImport::firstOrFail();
        $import->update(['status' => PedigreeImport::READING]);

        // Removed by the user before the reading finished
        PedigreeImport::whereKey($import->id)->update(['status' => PedigreeImport::DISCARDED]);
        app(PedigreeImportRunner::class)->read($import);

        $this->assertSame(PedigreeImport::DISCARDED, $import->fresh()->status);
    }

    public function test_a_sheet_left_reading_is_put_back_in_the_queue_once(): void
    {
        $this->answer = new PedigreeReadFailed('Not yet');
        $this->upload();
        $import = PedigreeImport::firstOrFail();
        $import->update(['status' => PedigreeImport::READING, 'started_at' => now()->subMinutes(10), 'attempts' => 1]);

        $this->answer = self::reading();
        $this->get(route('pedigree-imports.index'));
        $this->assertSame(PedigreeImport::READY, $import->fresh()->status);

        PedigreeImport::whereKey($import->id)->update(['status' => PedigreeImport::READING, 'started_at' => now()->subMinutes(10), 'attempts' => 2]);
        $this->get(route('pedigree-imports.index'));
        $this->assertSame(PedigreeImport::FAILED, $import->fresh()->status);
    }

    public function test_deleting_the_account_deletes_its_uploaded_sheets(): void
    {
        $import = $this->readyImport();
        Storage::disk('r2')->assertExists($import->file_path);

        $this->user->delete();

        $this->assertSame([], Storage::disk('r2')->allFiles());
    }

    public function test_the_reader_schema_is_valid_for_structured_outputs(): void
    {
        $check = function (array $schema, string $where) use (&$check) {
            if (($schema['type'] ?? null) === 'object') {
                $this->assertFalse($schema['additionalProperties'], "{$where} must not allow other properties");
                $this->assertEqualsCanonicalizing(array_keys($schema['properties']), $schema['required'], "{$where} must require every property");

                foreach ($schema['properties'] as $name => $property) {
                    $check($property, "{$where}.{$name}");
                }
            }

            foreach ([...($schema['anyOf'] ?? []), ...(isset($schema['items']) ? [$schema['items']] : [])] as $part) {
                $check($part, $where);
            }
        };

        $check(PedigreeReader::schema(), 'reading');
        $this->assertCount(31, PedigreeReader::positions());
        $this->assertStringContainsString('ace_pigeon', PedigreeReader::instructions());
    }
}
