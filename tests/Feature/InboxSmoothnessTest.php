<?php

namespace Tests\Feature;

use App\Filament\Pages\Inbox;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for the inbox smoothness improvements:
 * full-message search, "Load more conversations" list expansion, the
 * auto-growing composer, and the Android shell's keyboard-aware resize.
 */
class InboxSmoothnessTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Smoothness Co',
            'slug' => 'smoothness-co',
            'invoice_prefix' => 'SMOOTH',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($user);
    }

    protected function createConversation(string $name): Conversation
    {
        return Conversation::query()->create([
            'provider' => 'manual',
            'contact_name' => $name,
            'contact_phone' => '01700000000',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
    }

    public function test_search_finds_conversations_by_message_body(): void
    {
        $matching = $this->createConversation('Bulk Hardware');
        $other = $this->createConversation('Unrelated Traders');

        ConversationMessage::query()->create([
            'conversation_id' => $matching->getKey(),
            'direction' => 'incoming',
            'type' => 'text',
            'body' => 'Do you deliver the solar panel kits to Khulna?',
            'delivery_status' => 'received',
            'sent_at' => now(),
        ]);

        ConversationMessage::query()->create([
            'conversation_id' => $other->getKey(),
            'direction' => 'incoming',
            'type' => 'text',
            'body' => 'Thanks, all good here.',
            'delivery_status' => 'received',
            'sent_at' => now(),
        ]);

        Livewire::test(Inbox::class)
            ->set('search', 'solar panel kits')
            ->assertSee('Bulk Hardware')
            ->assertDontSee('Unrelated Traders');
    }

    public function test_load_more_conversations_expands_the_visible_list(): void
    {
        for ($index = 1; $index <= 35; $index++) {
            $this->createConversation(sprintf('Bulk Contact %02d', $index));
        }

        Livewire::test(Inbox::class)
            ->assertSet('conversationsPerPage', 30)
            ->assertSee('Bulk Contact 35')
            ->assertSee('Load more conversations')
            ->call('loadMoreConversations')
            ->assertSet('conversationsPerPage', 60)
            ->assertSee('Bulk Contact 01')
            ->assertSee('Bulk Contact 35')
            ->assertDontSee('Load more conversations');
    }

    public function test_composer_height_resets_when_conversations_change(): void
    {
        $view = File::get(resource_path('views/filament/pages/inbox.blade.php'));

        // The auto-grow composer must collapse back to one line whenever the
        // thread changes (new conversation or sent message clears the draft),
        // otherwise a long draft's height leaks into the next contact.
        $this->assertStringContainsString('x-ref="replyTextarea"', $view);
        $this->assertStringContainsString('resetComposerHeight(); stickToBottom = true; queueBottomSync();', $view);
        $this->assertStringContainsString('resetComposerHeight(); $refs.threadHeading?.focus();', $view);
    }

    public function test_android_shell_requests_keyboard_aware_window_resize(): void
    {
        // The Capacitor WebView must resize its viewport when the soft
        // keyboard opens (adjustResize) — without it the inbox composer hides
        // behind the keyboard on the mobile app.
        $manifest = File::get(base_path('android/app/src/main/AndroidManifest.xml'));

        $this->assertStringContainsString('android:windowSoftInputMode="adjustResize"', $manifest);
    }
}
