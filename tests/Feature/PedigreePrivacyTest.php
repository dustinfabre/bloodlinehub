<?php

namespace Tests\Feature;

use App\Http\Middleware\NoIndex;
use App\Models\Pigeon;
use App\Models\User;
use App\Services\Pedigree\GoneBirdPedigrees;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Shared pedigrees stay out of search engines, and a public pedigree can be made private as soon as its bird goes
 * missing or dies, so whoever finds a lost bird can't trace it.
 */
class PedigreePrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->me = User::factory()->create();
    }

    private function bird(string $ring, string $visibility = 'public', array $attributes = [], ?User $owner = null): Pigeon
    {
        $pigeon = Pigeon::create([
            'user_id' => ($owner ?? $this->me)->id,
            'status' => 'stock',
            'ring_number' => $ring,
            ...$attributes,
        ]);

        $pigeon->pedigreeRecord()->update(match ($visibility) {
            'public' => ['is_public' => true],
            'password' => ['is_public' => false, 'password' => 'secret-password'],
            default => ['is_public' => false],
        });

        return $pigeon;
    }

    public function test_shared_pedigrees_tell_search_engines_not_to_index_them(): void
    {
        $public = $this->bird('PH-1', 'public');
        $private = $this->bird('PH-2', 'private');
        $protected = $this->bird('PH-3', 'password');

        $this->get(route('pedigrees.shared', $public->pedigree->serial))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', NoIndex::DIRECTIVES);
        $this->get(route('pedigrees.shared', $private->pedigree->serial))
            ->assertForbidden()
            ->assertHeader('X-Robots-Tag', NoIndex::DIRECTIVES);
        $this->get(route('pedigrees.shared', $protected->pedigree->serial))
            ->assertForbidden()
            ->assertHeader('X-Robots-Tag', NoIndex::DIRECTIVES);
        $this->post(route('pedigrees.unlock', $protected->pedigree->serial), ['password' => 'secret-password'])
            ->assertRedirect()
            ->assertHeader('X-Robots-Tag', NoIndex::DIRECTIVES);

        // The owner's own view of the shared page too
        $this->actingAs($this->me)
            ->get(route('pedigrees.shared', $private->pedigree->serial))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', NoIndex::DIRECTIVES);
    }

    public function test_marking_a_bird_missing_offers_to_make_its_public_pedigree_private(): void
    {
        $bird = $this->bird('PH-1', 'public');

        $this->actingAs($this->me)
            ->patch(route('pigeons.status', $bird), ['status' => 'missing'])
            ->assertSessionHas(GoneBirdPedigrees::SESSION_KEY, fn (array $prompt) => $prompt['pigeons'] === [
                ['id' => $bird->id, 'label' => 'PH-1', 'status' => 'missing'],
            ] && $prompt['id'] !== '');
    }

    public function test_marking_a_bird_deceased_on_the_edit_form_offers_it_too(): void
    {
        $bird = $this->bird('PH-1', 'public');

        $this->actingAs($this->me)
            ->put(route('pigeons.update', $bird), ['status' => 'deceased', 'ring_number' => 'PH-1'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas(GoneBirdPedigrees::SESSION_KEY, fn (array $prompt) => $prompt['pigeons'][0]['status'] === 'deceased');
    }

    public function test_no_prompt_when_the_pedigree_is_already_hidden_or_the_bird_is_still_in_the_loft(): void
    {
        $private = $this->bird('PH-1', 'private');
        $protected = $this->bird('PH-2', 'password');
        $racing = $this->bird('PH-3', 'public');
        $noPedigree = Pigeon::create(['user_id' => $this->me->id, 'status' => 'stock', 'ring_number' => 'PH-4']);

        $this->actingAs($this->me);
        $this->patch(route('pigeons.status', $private), ['status' => 'missing'])->assertSessionMissing(GoneBirdPedigrees::SESSION_KEY);
        $this->patch(route('pigeons.status', $protected), ['status' => 'missing'])->assertSessionMissing(GoneBirdPedigrees::SESSION_KEY);
        $this->patch(route('pigeons.status', $racing), ['status' => 'racing'])->assertSessionMissing(GoneBirdPedigrees::SESSION_KEY);
        $this->patch(route('pigeons.status', $noPedigree), ['status' => 'missing'])->assertSessionMissing(GoneBirdPedigrees::SESSION_KEY);
    }

    public function test_saving_an_edit_that_leaves_a_missing_bird_missing_does_not_ask_again(): void
    {
        $bird = $this->bird('PH-1', 'public', ['status' => 'missing']);

        $this->actingAs($this->me)
            ->put(route('pigeons.update', $bird), ['status' => 'missing', 'ring_number' => 'PH-1', 'name' => 'RENAMED'])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing(GoneBirdPedigrees::SESSION_KEY);
    }

    public function test_a_loft_check_offers_it_for_every_bird_it_marks_missing_with_a_public_pedigree(): void
    {
        $seen = $this->bird('PH-1', 'public');
        $lostPublic = $this->bird('PH-2', 'public');
        $lostPrivate = $this->bird('PH-3', 'private');
        $lostAlsoPublic = $this->bird('PH-4', 'public');

        $this->actingAs($this->me)
            ->post(route('loft-checks.store'), [
                'present' => [$seen->id],
                'missing' => [$lostPublic->id, $lostPrivate->id, $lostAlsoPublic->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas(GoneBirdPedigrees::SESSION_KEY, fn (array $prompt) => collect($prompt['pigeons'])->pluck('id')->all() === [
                $lostPublic->id,
                $lostAlsoPublic->id,
            ]);
    }

    public function test_the_prompt_reaches_the_next_page_only(): void
    {
        $bird = $this->bird('PH-1', 'public');

        $this->actingAs($this->me)
            ->from(route('pigeons.index'))
            ->patch(route('pigeons.status', $bird), ['status' => 'missing'])
            ->assertRedirect(route('pigeons.index'));

        $this->get(route('pigeons.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pedigreePrompt.pigeons.0.id', $bird->id)
                ->where('pedigreePrompt.pigeons.0.status', 'missing')
            );

        // Asked once: the page after that no longer carries it
        $this->get(route('pigeons.index'))
            ->assertInertia(fn (Assert $page) => $page->where('pedigreePrompt', null));
    }

    public function test_make_private_only_changes_my_own_public_pedigrees(): void
    {
        $mine = $this->bird('PH-1', 'public', ['status' => 'missing']);
        $myProtected = $this->bird('PH-2', 'password', ['status' => 'missing']);
        $theirs = $this->bird('PH-3', 'public', owner: User::factory()->create());

        $this->actingAs($this->me)
            ->patch(route('pedigrees.makePrivate'), ['pigeon_ids' => [$mine->id, $myProtected->id, $theirs->id]])
            ->assertRedirect()
            ->assertSessionHas('success', 'Pedigree is now private.');

        $this->assertSame('private', $mine->pedigree->fresh()->visibility());
        // A password-protected pedigree keeps its password
        $this->assertSame('password', $myProtected->pedigree->fresh()->visibility());
        $this->assertSame('public', $theirs->pedigree->fresh()->visibility());
    }
}
