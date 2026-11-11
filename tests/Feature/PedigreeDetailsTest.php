<?php

namespace Tests\Feature;

use App\Models\BreederProfile;
use App\Models\Pigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PedigreeDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->premium()->create(['name' => 'Juan Dela Cruz', 'email' => 'juan.personal@example.com']);
    }

    private function details(array $overrides = []): array
    {
        return [
            'display_name' => 'Dela Cruz Loft',
            'phone' => '+63 917 123 4567',
            'email' => 'loft@example.com',
            'address' => 'Lipa City, Batangas',
            'show_phone' => true,
            'show_email' => true,
            'show_address' => true,
            ...$overrides,
        ];
    }

    private function pigeon(): Pigeon
    {
        return Pigeon::create(['user_id' => $this->user->id, 'status' => 'stock', 'ring_number' => 'PHA-2025-1']);
    }

    public function test_settings_start_empty_and_never_use_the_login_email(): void
    {
        $this->actingAs($this->user)
            ->get(route('pedigree-details.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/PedigreeDetails')
                ->where('accountName', 'Juan Dela Cruz')
                ->where('details.display_name', null)
                ->where('details.phone', null)
                ->where('details.email', null)
                ->where('details.address', null)
                ->where('details.show_phone', true)
            );

        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $this->pigeon()))
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.breeder', 'Juan Dela Cruz')
                ->where('meta.contact', ['phone' => null, 'email' => null, 'address' => null])
            );
    }

    public function test_the_pedigree_page_prompts_for_contact_details_until_some_are_entered(): void
    {
        $pigeon = $this->pigeon();

        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $pigeon))
            ->assertInertia(fn (Assert $page) => $page->where('hasContactDetails', false));

        // Entered but hidden is a choice, not a gap: no prompt
        BreederProfile::create(['user_id' => $this->user->id, ...$this->details([
            'phone' => null,
            'address' => null,
            'show_email' => false,
        ])]);

        $this->actingAs($this->user)
            ->get(route('pigeons.pedigree', $pigeon))
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasContactDetails', true)
                ->where('meta.contact', ['phone' => null, 'email' => null, 'address' => null])
            );
    }

    public function test_details_are_saved_and_shown_on_every_pedigree(): void
    {
        $this->actingAs($this->user)
            ->patch(route('pedigree-details.update'), $this->details())
            ->assertRedirect(route('pedigree-details.edit'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('breeder_profiles', [
            'user_id' => $this->user->id,
            'display_name' => 'Dela Cruz Loft',
            'phone' => '+63 917 123 4567',
        ]);

        foreach ([$this->pigeon(), $this->pigeon()] as $pigeon) {
            $this->actingAs($this->user)
                ->get(route('pigeons.pedigree', $pigeon))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('meta.breeder', 'Dela Cruz Loft')
                    ->where('meta.contact.phone', '+63 917 123 4567')
                    ->where('meta.contact.email', 'loft@example.com')
                    ->where('meta.contact.address', 'Lipa City, Batangas')
                );
        }

        // Saving again updates the same record
        $this->actingAs($this->user)->patch(route('pedigree-details.update'), $this->details(['display_name' => '']));
        $this->assertSame(1, BreederProfile::count());
        $this->assertNull($this->user->breederProfile()->first()->display_name);
    }

    public function test_hidden_details_never_reach_a_shared_pedigree(): void
    {
        $this->actingAs($this->user)->patch(route('pedigree-details.update'), $this->details([
            'show_phone' => false,
            'show_address' => false,
        ]));
        $pigeon = $this->pigeon();
        $pigeon->pedigreeRecord()->update(['is_public' => true]);

        auth()->logout();

        $response = $this->get(route('pedigrees.shared', $pigeon->fresh()->pedigree->serial))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.breeder', 'Dela Cruz Loft')
                ->where('meta.contact', ['phone' => null, 'email' => 'loft@example.com', 'address' => null])
            );

        $html = $response->getContent();
        $this->assertStringNotContainsString('917 123 4567', $html);
        $this->assertStringNotContainsString('Lipa City', $html);
        $this->assertStringNotContainsString('juan.personal@example.com', $html);
    }

    public function test_details_are_validated(): void
    {
        $this->actingAs($this->user)
            ->patch(route('pedigree-details.update'), $this->details([
                'phone' => 'call me maybe',
                'email' => 'not-an-email',
                'address' => str_repeat('a', 301),
                'show_email' => 'sometimes',
            ]))
            ->assertSessionHasErrors(['phone', 'email', 'address', 'show_email']);

        $this->actingAs($this->user)
            ->patch(route('pedigree-details.update'), $this->details(['phone' => '(0917) 123-4567 / 0928 765 4321']))
            ->assertSessionHasNoErrors();
    }

    public function test_guests_cannot_open_the_settings(): void
    {
        $this->get(route('pedigree-details.edit'))->assertRedirect(route('login'));
        $this->patch(route('pedigree-details.update'), $this->details())->assertRedirect(route('login'));
        $this->assertSame(0, BreederProfile::count());
    }
}
