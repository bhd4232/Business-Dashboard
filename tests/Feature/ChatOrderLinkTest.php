<?php

namespace Tests\Feature;

use App\Models\ChatOrderLink;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatOrderLinkTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->company = Company::query()->create([
            'name' => 'Chat Order Co',
            'slug' => 'chat-order-co',
            'invoice_prefix' => 'CO',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);

        $this->product = Product::query()->create([
            'name' => 'Chat Product',
            'sku' => 'CO-PROD-001',
            'price' => 500,
            'sale_price' => 500,
            'cost_price' => 300,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        StockMovement::query()->create([
            'company_id' => $this->company->getKey(),
            'product_id' => $this->product->getKey(),
            'type' => 'opening',
            'quantity' => 10,
            'reference_type' => Product::class,
            'reference_id' => $this->product->getKey(),
            'note' => 'Chat order test opening stock',
        ]);
    }

    protected function makeLink(array $overrides = []): ChatOrderLink
    {
        app(CompanyContext::class)->set($this->company);

        $lead = Lead::query()->create([
            'name' => 'Chat Lead',
            'phone' => '01898765432',
            'source' => 'whatsapp',
        ]);

        $conversation = Conversation::query()->create([
            'provider' => 'manual',
            'contact_name' => 'Chat Lead',
            'contact_phone' => '01898765432',
            'lead_id' => $lead->getKey(),
            'status' => 'open',
        ]);

        $link = ChatOrderLink::query()->create(array_merge([
            'conversation_id' => $conversation->getKey(),
            'lead_id' => $lead->getKey(),
            'prefill' => [
                'items' => [[
                    'product_id' => $this->product->getKey(),
                    'name' => $this->product->name,
                    'quantity' => 2,
                    'unit_price' => 500,
                ]],
                'name' => 'Chat Lead',
                'phone' => '01898765432',
            ],
        ], $overrides));

        app(CompanyContext::class)->clear();

        return $link;
    }

    public function test_valid_token_shows_prefilled_form(): void
    {
        $link = $this->makeLink();

        $this->get("/o/{$link->token}")
            ->assertOk()
            ->assertSee('Chat Product')
            ->assertSee('Chat Lead')
            ->assertSee('01898765432');

        $this->assertNotNull($link->fresh()->opened_at);
    }

    public function test_expired_token_shows_closed_page(): void
    {
        $link = $this->makeLink(['expires_at' => now()->subDay()]);

        $this->get("/o/{$link->token}")
            ->assertOk()
            ->assertSee('মেয়াদ শেষ');
    }

    public function test_submitting_creates_chat_order_and_locks_the_link(): void
    {
        $link = $this->makeLink();

        $this->post("/o/{$link->token}", [
            'name' => 'Chat Lead',
            'phone' => '01898765432',
            'address' => 'House 1, Dhaka',
            'quantities' => [0 => 3],
        ])->assertOk()->assertSee('অর্ডার নম্বর');

        $link = $link->fresh();
        $this->assertNotNull($link->converted_order_id);

        $order = Order::withoutGlobalScopes()->find($link->converted_order_id);
        $this->assertSame(Order::SOURCE_CHAT, $order->source);
        $this->assertSame(3, (int) $order->items()->sum('quantity'));
        $this->assertSame($this->company->getKey(), $order->company_id);

        $lead = Lead::withoutGlobalScopes()->find($link->lead_id);
        $this->assertSame('won', $lead->status);
        $this->assertNotNull($lead->converted_customer_id);
        $this->assertSame($order->getKey(), $lead->converted_order_id);

        // Confirmation message is archived on the conversation.
        $this->assertTrue(
            ConversationMessage::query()
                ->where('conversation_id', $link->conversation_id)
                ->where('direction', 'outgoing')
                ->where('type', 'order_form')
                ->exists(),
        );
    }

    public function test_converted_link_cannot_create_a_second_order(): void
    {
        $link = $this->makeLink();

        $payload = [
            'name' => 'Chat Lead',
            'phone' => '01898765432',
            'address' => 'House 1, Dhaka',
        ];

        $this->post("/o/{$link->token}", $payload)->assertOk();
        $this->post("/o/{$link->token}", $payload)->assertOk()->assertSee('ইতিমধ্যে');

        $this->assertSame(1, Order::withoutGlobalScopes()->count());
    }

    public function test_honeypot_field_rejects_bots(): void
    {
        $link = $this->makeLink();

        $this->post("/o/{$link->token}", [
            'name' => 'Bot',
            'phone' => '0000',
            'address' => 'nowhere',
            'website' => 'spam',
        ])->assertStatus(422);

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->get('/o/does-not-exist')->assertNotFound();
    }
}
