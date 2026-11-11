<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The feedback page and the admin inbox it feeds.
 */
class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected User $me;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('r2');

        $this->me = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    public function test_the_feedback_page_remembers_where_the_user_came_from(): void
    {
        $this->actingAs($this->me)->get(route('feedback.create', ['from' => '/pigeons/12']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('feedback/Create')->where('from', '/pigeons/12'));

        $this->actingAs($this->me)->get(route('feedback.create', ['from' => 'https://evil.example']))
            ->assertInertia(fn (Assert $page) => $page->where('from', null));
    }

    public function test_feedback_is_saved_with_a_screenshot(): void
    {
        $this->actingAs($this->me)->post(route('feedback.store'), [
            'category' => 'bug',
            'message' => 'The pedigree print cuts off the last row.',
            'page_url' => '/pigeons/12/print-pedigree',
            'screenshot' => UploadedFile::fake()->image('screen.png', 1200, 800),
        ])->assertSessionHasNoErrors();

        $feedback = Feedback::sole();
        $this->assertSame($this->me->id, $feedback->user_id);
        $this->assertSame('bug', $feedback->category);
        $this->assertSame('/pigeons/12/print-pedigree', $feedback->page_url);
        Storage::disk('r2')->assertExists($feedback->screenshot_path);
    }

    public function test_outside_page_urls_are_dropped(): void
    {
        $this->actingAs($this->me)->post(route('feedback.store'), [
            'category' => 'idea', 'message' => 'More colors please', 'page_url' => '//evil.example/x',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Feedback::sole()->page_url);
    }

    public function test_feedback_needs_a_known_category_and_a_message(): void
    {
        $this->actingAs($this->me)->post(route('feedback.store'), ['category' => 'spam', 'message' => ''])
            ->assertSessionHasErrors(['category', 'message']);
    }

    public function test_the_inbox_lists_marks_and_archives_feedback(): void
    {
        $feedback = $this->me->feedback()->create(['category' => 'question', 'message' => 'How do I print?']);

        $this->actingAs($this->admin)->get(route('admin.inbox.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Inbox')
                ->has('messages.data', 1)
                ->where('messages.data.0.read', false)
                ->where('messages.data.0.user.email', $this->me->email)
                ->where('counts.unread', 1));

        $this->actingAs($this->admin)->patch(route('admin.inbox.update', $feedback), ['read' => true]);
        $this->assertNotNull($feedback->fresh()->read_at);

        $this->actingAs($this->admin)->patch(route('admin.inbox.update', $feedback), ['archived' => true]);
        $this->assertNotNull($feedback->fresh()->archived_at);

        $this->actingAs($this->admin)->get(route('admin.inbox.index'))
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 0)->where('counts.archived', 1));
        $this->actingAs($this->admin)->get(route('admin.inbox.index', ['folder' => 'archived']))
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1));

        // The sender sees that it was read
        $this->actingAs($this->me)->get(route('feedback.create'))
            ->assertInertia(fn (Assert $page) => $page->where('recent.0.seen', true));
    }

    public function test_only_admins_see_screenshots(): void
    {
        Storage::disk('r2')->put('feedback/s.webp', 'img');
        $feedback = $this->me->feedback()->create(['category' => 'bug', 'message' => 'Broken', 'screenshot_path' => 'feedback/s.webp']);

        $this->actingAs($this->admin)->get(route('admin.inbox.screenshot', $feedback))->assertOk();
        $this->actingAs($this->me)->get(route('admin.inbox.screenshot', $feedback))->assertNotFound();
    }

    public function test_deleting_feedback_or_the_account_removes_screenshots(): void
    {
        Storage::disk('r2')->put('feedback/a.webp', 'img');
        Storage::disk('r2')->put('feedback/b.webp', 'img');
        $first = $this->me->feedback()->create(['category' => 'bug', 'message' => 'One', 'screenshot_path' => 'feedback/a.webp']);
        $this->me->feedback()->create(['category' => 'bug', 'message' => 'Two', 'screenshot_path' => 'feedback/b.webp']);

        $this->actingAs($this->admin)->delete(route('admin.inbox.destroy', $first));
        Storage::disk('r2')->assertMissing('feedback/a.webp');

        $this->me->delete();
        Storage::disk('r2')->assertMissing('feedback/b.webp');
        $this->assertSame(0, Feedback::count());
    }
}
