<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\FeedbackLabel;
use App\Models\User;
use App\Notifications\FeedbackStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Organizing the admin inbox: stars, statuses per category (with an email to the senders), labels, private notes,
 * "same as" repeats with request counts, and search.
 */
class InboxOrganizingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutDefer();
        Notification::fake();

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    private function feedback(string $category = 'idea', string $message = 'Add a dark pedigree theme', ?User $from = null): Feedback
    {
        return ($from ?? User::factory()->create())->feedback()->create(['category' => $category, 'message' => $message]);
    }

    private function inbox(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('admin.inbox.index', $query));
    }

    public function test_only_admins_can_organize(): void
    {
        $feedback = $this->feedback();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.inbox.status', $feedback), ['status' => 'planned'])->assertNotFound();
        $this->actingAs($user)->post(route('admin.inbox.duplicate', $feedback), ['duplicate_of' => null])->assertNotFound();
        $this->actingAs($user)->post(route('admin.inbox.labels.store'), ['name' => 'x', 'color' => 'red'])->assertNotFound();
        $this->actingAs($user)->getJson(route('admin.inbox.lookup'))->assertNotFound();
        $this->assertSame('new', $feedback->fresh()->status);
    }

    public function test_starred_messages_have_their_own_folder_even_when_archived(): void
    {
        $feedback = $this->feedback();

        $this->actingAs($this->admin)->patch(route('admin.inbox.update', $feedback), ['starred' => true, 'archived' => true]);
        $this->assertNotNull($feedback->fresh()->starred_at);

        $this->inbox(['folder' => 'starred'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('messages.data', 1)
                ->where('messages.data.0.starred', true)
                ->where('counts.starred', 1));

        $this->actingAs($this->admin)->patch(route('admin.inbox.update', $feedback), ['starred' => false]);
        $this->assertNull($feedback->fresh()->starred_at);
    }

    public function test_the_status_must_fit_the_category(): void
    {
        $bug = $this->feedback('bug', 'Print cuts off');

        $this->actingAs($this->admin)->post(route('admin.inbox.status', $bug), ['status' => 'planned'])
            ->assertSessionHasErrors('status');

        $this->actingAs($this->admin)->post(route('admin.inbox.status', $bug), ['status' => 'fixed'])
            ->assertSessionHasNoErrors();

        $bug->refresh();
        $this->assertSame('fixed', $bug->status);
        $this->assertSame('Fixed', $bug->statusLabel());
        $this->assertTrue($bug->isClosed());
        $this->assertNotNull($bug->status_changed_at);
        Notification::assertNothingSent();
    }

    public function test_open_and_closed_filters(): void
    {
        $open = $this->feedback('bug', 'Still broken');
        $fixed = $this->feedback('bug', 'Was broken');
        $fixed->forceFill(['status' => 'fixed'])->save();

        $this->inbox(['status' => 'open'])
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1)->where('messages.data.0.id', $open->id));
        $this->inbox(['status' => 'closed'])
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1)->where('messages.data.0.id', $fixed->id));
        $this->inbox(['category' => 'bug', 'status' => 'fixed'])
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1)->where('filters.status', 'fixed'));
    }

    public function test_marking_a_repeat_counts_it_on_the_first_message(): void
    {
        $first = $this->feedback('idea', 'Export to Excel');
        $first->forceFill(['status' => 'planned'])->save();
        $repeat = $this->feedback('idea', 'Please add Excel export');

        $this->actingAs($this->admin)->post(route('admin.inbox.duplicate', $repeat), ['duplicate_of' => $first->id])
            ->assertSessionHasNoErrors();

        $repeat->refresh();
        $this->assertSame($first->id, $repeat->duplicate_of_id);
        $this->assertNotNull($repeat->archived_at);
        $this->assertNotNull($repeat->read_at);
        $this->assertSame('planned', $repeat->status);

        $this->inbox(['category' => 'idea'])
            ->assertInertia(fn (Assert $page) => $page
                ->has('messages.data', 1)
                ->where('messages.data.0.id', $first->id)
                ->where('messages.data.0.requests', 2)
                ->has('messages.data.0.also_asked', 1));

        $this->inbox(['folder' => 'archived'])
            ->assertInertia(fn (Assert $page) => $page->where('messages.data.0.duplicate_of.id', $first->id));
    }

    public function test_repeats_always_point_at_the_first_message(): void
    {
        $first = $this->feedback();
        $second = $this->feedback();
        $third = $this->feedback();

        // Third is a repeat of second, then second turns out to repeat first: both now count on first
        $this->actingAs($this->admin)->post(route('admin.inbox.duplicate', $third), ['duplicate_of' => $second->id]);
        $this->actingAs($this->admin)->post(route('admin.inbox.duplicate', $second), ['duplicate_of' => $first->id]);
        $this->assertSame($first->id, $third->fresh()->duplicate_of_id);

        // Picking a repeat points at its first message
        $fourth = $this->feedback();
        $this->actingAs($this->admin)->post(route('admin.inbox.duplicate', $fourth), ['duplicate_of' => $third->id]);
        $this->assertSame($first->id, $fourth->fresh()->duplicate_of_id);
        $this->assertSame(3, $first->duplicates()->count());

        $this->actingAs($this->admin)->post(route('admin.inbox.duplicate', $first), ['duplicate_of' => $first->id])
            ->assertSessionHasErrors('duplicate_of');
        $this->actingAs($this->admin)->post(route('admin.inbox.duplicate', $first), ['duplicate_of' => $second->id])
            ->assertSessionHasErrors('duplicate_of');
    }

    public function test_a_repeat_can_be_unmarked(): void
    {
        $first = $this->feedback();
        $repeat = $this->feedback();
        $this->actingAs($this->admin)->post(route('admin.inbox.duplicate', $repeat), ['duplicate_of' => $first->id]);

        $this->actingAs($this->admin)->post(route('admin.inbox.duplicate', $repeat), ['duplicate_of' => null]);

        $this->assertNull($repeat->fresh()->duplicate_of_id);
        $this->assertNull($repeat->fresh()->archived_at);
    }

    public function test_most_requested_sorts_by_repeats(): void
    {
        $popular = $this->feedback('idea', 'Popular');
        $newer = $this->feedback('idea', 'Newer, one request');
        $this->travel(1)->minutes();
        foreach (range(1, 2) as $i) {
            $this->feedback('idea', "Me too {$i}")->forceFill(['duplicate_of_id' => $popular->id, 'archived_at' => now()])->save();
        }

        $this->inbox(['category' => 'idea', 'sort' => 'requested'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.data.0.id', $popular->id)
                ->where('messages.data.0.requests', 3)
                ->where('messages.data.1.id', $newer->id));
    }

    public function test_a_status_change_moves_the_repeats_too_and_can_email_every_sender(): void
    {
        $first = $this->feedback('idea', 'Export to Excel');
        $repeat = $this->feedback('idea', 'Excel please');
        $this->actingAs($this->admin)->post(route('admin.inbox.duplicate', $repeat), ['duplicate_of' => $first->id]);

        $this->actingAs($this->admin)->post(route('admin.inbox.status', $first), [
            'status' => 'done',
            'notify' => true,
            'note' => 'Find it under Pigeons → Export.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('done', $repeat->fresh()->status);

        foreach ([$first->user, $repeat->user] as $sender) {
            Notification::assertSentTo($sender, FeedbackStatusChanged::class, function (FeedbackStatusChanged $notification) use ($sender) {
                $mail = $notification->toMail($sender);

                return $mail->subject === 'Done: what you asked for'
                    && in_array('Find it under Pigeons → Export.', $mail->introLines, true)
                    && str_contains((string) $mail->render(), 'Export to Excel');
            });
        }
    }

    public function test_senders_cant_be_emailed_that_something_is_new(): void
    {
        $bug = $this->feedback('bug', 'Broken');
        $bug->forceFill(['status' => 'fixed'])->save();

        $this->actingAs($this->admin)->post(route('admin.inbox.status', $bug), ['status' => 'new', 'notify' => true])
            ->assertSessionHasErrors('notify');
        Notification::assertNothingSent();
    }

    public function test_labels_group_messages(): void
    {
        $this->actingAs($this->admin)->post(route('admin.inbox.labels.store'), ['name' => ' pedigree ', 'color' => 'blue'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.inbox.labels.store'), ['name' => 'pedigree', 'color' => 'red'])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('admin.inbox.labels.store'), ['name' => 'billing', 'color' => 'neon'])
            ->assertSessionHasErrors('color');

        $label = FeedbackLabel::sole();
        $this->assertSame('pedigree', $label->name);
        $tagged = $this->feedback();
        $this->feedback();

        $this->actingAs($this->admin)->patch(route('admin.inbox.update', $tagged), ['labels' => [$label->id]]);

        $this->inbox(['label' => $label->id])
            ->assertInertia(fn (Assert $page) => $page
                ->has('messages.data', 1)
                ->where('messages.data.0.labels', [$label->id])
                ->where('labels.0.feedback_count', 1));

        $this->actingAs($this->admin)->patch(route('admin.inbox.labels.update', $label), ['name' => 'pedigrees', 'color' => 'green']);
        $this->assertSame('green', $label->fresh()->color);

        $this->actingAs($this->admin)->delete(route('admin.inbox.labels.destroy', $label));
        $this->assertSame(0, FeedbackLabel::count());
        $this->assertNotNull($tagged->fresh());
    }

    public function test_private_notes_are_saved_and_searchable(): void
    {
        $feedback = $this->feedback('bug', 'Crash on save');

        $this->actingAs($this->admin)->patch(route('admin.inbox.update', $feedback), ['admin_note' => '  Asked for a screenshot  ']);
        $this->assertSame('Asked for a screenshot', $feedback->fresh()->admin_note);

        $this->inbox(['q' => 'screenshot'])
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1)->where('messages.data.0.admin_note', 'Asked for a screenshot'));
    }

    public function test_search_finds_text_senders_and_numbers(): void
    {
        $juan = User::factory()->create(['name' => 'Juan dela Cruz']);
        $match = $this->feedback('bug', 'The OLR standings are wrong', $juan);
        $this->feedback('idea', 'Something else');

        $this->inbox(['q' => 'standings'])->assertInertia(fn (Assert $page) => $page->has('messages.data', 1));
        $this->inbox(['q' => 'dela cruz'])->assertInertia(fn (Assert $page) => $page->has('messages.data', 1));
        $this->inbox(['q' => "#{$match->id}"])
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1)->where('messages.data.0.id', $match->id));
    }

    public function test_lookup_offers_earlier_first_messages_of_the_same_type(): void
    {
        $first = $this->feedback('idea', 'Export to Excel');
        $repeat = $this->feedback('idea', 'Excel too');
        $repeat->forceFill(['duplicate_of_id' => $first->id])->save();
        $this->feedback('bug', 'Excel button broken');
        $current = $this->feedback('idea', 'Excel export please');

        $this->actingAs($this->admin)
            ->getJson(route('admin.inbox.lookup', ['q' => 'excel', 'exclude' => $current->id, 'category' => 'idea']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $first->id)
            ->assertJsonPath('results.0.requests', 2);
    }

    public function test_the_sender_sees_where_their_message_stands(): void
    {
        $sender = User::factory()->create();
        $feedback = $this->feedback('bug', 'Broken', $sender);
        $feedback->forceFill(['status' => 'in_progress'])->save();

        $this->actingAs($sender)->get(route('feedback.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recent.0.status', 'Working on it')
                ->where('recent.0.closed', false));
    }
}
