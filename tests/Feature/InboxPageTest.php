<?php

namespace Tests\Feature;

use App\Filament\Pages\Inbox;
use App\Models\ChatOrderLink;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LegacyPrivateStoragePath;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanyContext;
use App\Support\CompanyMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class InboxPageTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Conversation $conversation;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->company = Company::query()->create([
            'name' => 'Inbox Co',
            'slug' => 'inbox-co',
            'invoice_prefix' => 'IB',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);

        $this->conversation = Conversation::query()->create([
            'provider' => 'manual',
            'contact_name' => 'Inbox Lead',
            'contact_phone' => '01911112222',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $this->product = Product::query()->create([
            'name' => 'Catalog Product',
            'sku' => 'IB-PROD-001',
            'price' => 750,
            'sale_price' => 750,
            'cost_price' => 400,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
            'image' => 'products/catalog-product.jpg',
        ]);

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($user);
    }

    public function test_send_reply_archives_message_and_keeps_conversation_open(): void
    {
        Livewire::test(Inbox::class)
            ->call('selectConversation', $this->conversation->getKey())
            ->set('replyBody', 'Hello from staff')
            ->call('sendReply')
            ->assertSet('selectedConversationId', $this->conversation->getKey())
            ->assertSet('replyBody', '');

        $this->assertTrue(
            ConversationMessage::query()
                ->where('conversation_id', $this->conversation->getKey())
                ->where('direction', 'outgoing')
                ->where('body', 'Hello from staff')
                ->exists(),
        );
    }

    public function test_send_order_form_keeps_conversation_selected_and_attaches_product_image(): void
    {
        Livewire::test(Inbox::class)
            ->call('selectConversation', $this->conversation->getKey())
            ->set('orderFormProductId', $this->product->getKey())
            ->set('orderFormQuantity', 3)
            ->call('sendOrderForm')
            ->assertSet('selectedConversationId', $this->conversation->getKey());

        $link = ChatOrderLink::query()->where('conversation_id', $this->conversation->getKey())->first();
        $this->assertNotNull($link);
        $this->assertSame('products/catalog-product.jpg', $link->prefill['items'][0]['image']);
        $this->assertSame(3, $link->prefill['items'][0]['quantity']);

        $message = ConversationMessage::query()
            ->where('conversation_id', $this->conversation->getKey())
            ->where('type', 'order_form')
            ->first();
        $this->assertNotNull($message);
        $this->assertStringContainsString($link->publicUrl(), $message->body);
        $this->assertStringContainsString('Catalog Product', $message->body);
        $this->assertNotNull($message->mediaImageUrl());
        $this->assertSame(
            CompanyMedia::publicUrl($this->product->image, $this->product),
            $message->mediaImageUrl(),
        );
    }

    public function test_media_image_url_resolves_urls_and_storage_paths(): void
    {
        $fromUrl = new ConversationMessage(['media_path' => 'https://example.com/img/p.jpg', 'media_mime' => 'image/*']);
        $this->assertSame('https://example.com/img/p.jpg', $fromUrl->mediaImageUrl());

        $rootRelativeUrl = new ConversationMessage(['media_path' => '/storage/products/p.jpg', 'media_mime' => 'image/jpeg']);
        $this->assertSame('/storage/products/p.jpg', $rootRelativeUrl->mediaImageUrl());

        LegacyPrivateStoragePath::query()->create([
            'path' => 'conversations/photo.png',
            'company_id' => $this->company->getKey(),
        ]);
        $fromStorage = ConversationMessage::query()->create([
            'conversation_id' => $this->conversation->getKey(),
            'direction' => 'incoming',
            'type' => 'image',
            'media_path' => 'conversations/photo.png',
            'media_mime' => 'image/png',
            'sent_at' => now(),
        ]);
        $this->assertSame(
            route('conversation-messages.media', ['message' => $fromStorage->getKey()]),
            $fromStorage->mediaImageUrl(),
        );

        $document = new ConversationMessage(['media_path' => 'conversations/invoice.pdf', 'media_mime' => 'application/pdf']);
        $this->assertNull($document->mediaImageUrl());

        $empty = new ConversationMessage(['media_path' => null]);
        $this->assertNull($empty->mediaImageUrl());
    }
}
