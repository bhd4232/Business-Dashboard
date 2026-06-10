# Module 12: Conversation & AI Agent Hub (Native Laravel)

## Overview

Full native Laravel implementation for customer conversations via WhatsApp and Messenger. Includes AI agent with 50-second human-first rule, visual drag-and-drop workflow builder, multi-provider WhatsApp API support, and real-time chat dashboard.

**No n8n dependency** — everything runs inside ZamZam ERP using Laravel 13 AI SDK, Laravel Reverb (WebSocket), and Laravel Queue (Redis).

---

## Architecture

```
┌──────────────┐     ┌──────────────┐
│  Messenger   │     │  WhatsApp    │
│  Customer    │     │  (Multi-     │
│              │     │  Provider)   │
└──────┬───────┘     └──────┬───────┘
       │ Webhook             │ Webhook
       ▼                    ▼
┌──────────────────────────────────────────────────────────┐
│                    ZamZam ERP (Laravel 13)                 │
│                                                           │
│  ┌──────────────────────────────────────────────────────┐ │
│  │         Webhook Controllers                          │ │
│  │  /webhooks/messenger                                │ │
│  │  /webhooks/whatsapp/{provider:slug}                 │ │
│  └──────────────────┬─────────────────────────────────┘ │
│                     ▼                                     │
│  ┌──────────────────────────────────────────────────────┐ │
│  │         ConversationService                          │ │
│  │  • Save message                                     │ │
│  │  • Match/identify customer                          │ │
│  │  • Start 50s timer (Redis Queue)                    │ │
│  └──────────────────┬─────────────────────────────────┘ │
│                     ▼                                     │
│  ┌──────────────────────────────────────────────────────┐ │
│  │         50s Human-First Rule                         │ │
│  │  • Human replies in 50s? → AI OFF                   │ │
│  │  • 50s timeout? → AI replies                        │ │
│  │  • Human interrupts AI? → AI OFF immediately         │ │
│  └──────────────────┬─────────────────────────────────┘ │
│                     ▼                                     │
│  ┌──────────────────────────────────────────────────────┐ │
│  │    Visual Workflow Engine                            │ │
│  │  • ChatbotWorkflow → nodes + edges (JSON)           │ │
│  │  • WorkflowExecutorService traverses graph           │ │
│  │  • 20+ node types (Trigger/AI/Action/Logic/Human)   │ │
│  │  • Vue Flow drag-and-drop editor                    │ │
│  └──────────────────┬─────────────────────────────────┘ │
│                     ▼                                     │
│  ┌──────────────────────────────────────────────────────┐ │
│  │    Laravel 13 AI SDK (Built-in)                      │ │
│  │  • Intent detection                                 │ │
│  │  • Entity extraction                                │ │
│  │  • Response generation (Bangla/En/Chinese)           │ │
│  │  • Context management                               │ │
│  │  • Tool calling (ERP actions)                        │ │
│  └──────────────────┬─────────────────────────────────┘ │
│                     ▼                                     │
│  ┌──────────────────────────────────────────────────────┐ │
│  │         AgentActionService                           │ │
│  │  • product_search()                                 │ │
│  │  • add_to_cart() / remove_from_cart()               │ │
│  │  • place_order()                                    │ │
│  │  • check_order_status()                             │ │
│  │  • check_payment_status()                           │ │
│  │  • send_payment_link()                              │ │
│  │  • request_return()                                 │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                           │
│  ┌──────────────────────────────────────────────────────┐ │
│  │    WhatsApp Multi-Provider Layer                     │ │
│  │  • Adapter pattern: one interface, many drivers      │ │
│  │  • 7 built-in drivers + no-code custom HTTP driver   │ │
│  │  • Dynamic switching + auto-fallback                 │ │
│  │  • Provider health monitoring                        │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                           │
│  ┌──────────────────────────────────────────────────────┐ │
│  │    Laravel Reverb (WebSocket)                        │ │
│  │  • Real-time chat in ERP dashboard                  │ │
│  │  • Live typing indicators                           │ │
│  │  • New message notifications                        │ │
│  └──────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────┘
```

---

## Package Requirements

| Package | Purpose |
|---------|---------|
| **laravel/ai** (official first-party package — `composer require laravel/ai`) | AI agent, intent detection, tool calling |
| **laravel/reverb** | WebSocket (real-time chat) |
| **laravel/echo** | Frontend WebSocket client |
| **laravel/sanctum** | API auth (mobile chat) |
| **@vue-flow/core** | Visual workflow builder (drag-drop node editor) |
| **@vue-flow/background** | Grid background |
| **@vue-flow/minimap** | Minimap |
| **@vue-flow/controls** | Zoom/pan controls |
| **twilio/sdk** | Twilio WhatsApp driver |
| **facebook/graph-sdk** | Messenger API |

---

## 50-Second Human-First Rule

```
Customer message arrives
      ↓
┌─────────────────────────────────────┐
│  50-second timer starts (Redis)      │
│                                     │
│  Human replies within 50s:           │
│    → AI OFF for this conversation    │
│    → AI will NOT reply               │
│    → Mark: "Human chatting"          │
│                                     │
│  50s passes, no human reply:         │
│    → AI auto-replies                 │
│    → Mark: "AI chatting"             │
│                                     │
│  Human messages while AI is active:  │
│    → AI OFF immediately              │
│    → Human takeover                  │
│    → 50s rule resets                 │
└─────────────────────────────────────┘
```

### Implementation

```php
class HumanFirstTimerService
{
    public function startTimer(int $conversationId, string $channelMessageId): void
    {
        Redis::setex(
            "human_first:{$conversationId}:{$channelMessageId}",
            50,
            json_encode(['conversation_id' => $conversationId])
        );

        HumanFirstTimeoutJob::dispatch($conversationId, $channelMessageId)
            ->delay(now()->addSeconds(50));
    }

    public function humanReplied(int $conversationId): void
    {
        // Cancel timer + AI off
        $keys = Redis::keys("human_first:{$conversationId}:*");
        foreach ($keys as $key) {
            Redis::del($key);
        }

        $conversation = Conversation::find($conversationId);
        $conversation->update([
            'is_ai_active' => false,
            'last_human_reply_at' => now(),
        ]);

        broadcast(new AiStatusChanged($conversationId, false));
    }

    public function aiReplied(int $conversationId): void
    {
        $conversation = Conversation::find($conversationId);
        $conversation->update([
            'is_ai_active' => true,
            'last_ai_reply_at' => now(),
        ]);
    }
}

class HumanFirstTimeoutJob implements ShouldQueue
{
    public function __construct(
        private int $conversationId,
        private string $channelMessageId
    ) {}

    public function handle(): void
    {
        if (Redis::exists("human_first:{$this->conversationId}:{$this->channelMessageId}")) {
            app(AgentReplyService::class)->generateAndSend(
                $this->conversationId,
                $this->channelMessageId
            );
        }
    }
}
```

---

## WhatsApp Multi-Provider Architecture

### Design: Adapter Pattern

```
┌─────────────────────────────────────────────────┐
│              WhatsAppChannelService              │
│              (single interface)                   │
└──────────────────────┬──────────────────────────┘
                       │
         ┌─────────────┼──────────────┐
         ▼             ▼              ▼
   ┌──────────┐  ┌──────────┐  ┌──────────┐
   │ Official │  │ Built-in │  │ Custom   │
   │ Cloud API│  │ Drivers  │  │ HTTP     │
   │ (Meta)   │  │ Registry │  │ Driver   │
   └──────────┘  └────┬─────┘  └──────────┘
                      │
        ┌──────┬──────┼──────┬───────┐
        ▼      ▼      ▼      ▼       ▼
   ┌───────┐┌──────┐┌─────┐┌──────┐┌───────┐
   │Twilio ││WAsend││Ultra││Maytap││Evol.  │
   │       ││er    ││Msg  ││i     ││API    │
   └───────┘└──────┘└─────┘└──────┘└───────┘
```

### Driver Interface

```php
interface WhatsAppDriverInterface
{
    public function sendMessage(string $to, string $text, array $options = []): WhatsAppMessageResult;
    public function sendMedia(string $to, string $mediaUrl, string $type, ?string $caption = null): WhatsAppMessageResult;
    public function sendTemplate(string $to, string $templateName, array $components = []): WhatsAppMessageResult;
    public function sendInteractiveButtons(string $to, string $body, array $buttons, ?string $header = null): WhatsAppMessageResult;
    public function sendListMessage(string $to, string $body, string $buttonText, array $rows, ?string $header = null): WhatsAppMessageResult;
    public function sendLocation(string $to, float $lat, float $lng, string $name, string $address): WhatsAppMessageResult;
    public function markAsRead(string $messageId): WhatsAppMessageResult;
    public function getProfile(string $phone): array;
    public function checkNumberExists(string $phone): bool;
    public function getWebhookVerifyToken(): string;
    public function parseIncomingWebhook(Request $request): WhatsAppIncomingMessage;
    public function verifyWebhook(Request $request): ?string;
}

readonly class WhatsAppMessageResult
{
    public function __construct(
        public bool $success,
        public ?string $messageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?array $rawResponse = null,
        public int $latencyMs = 0,
    ) {}
}

readonly class WhatsAppIncomingMessage
{
    public function __construct(
        public string $messageId,
        public string $from,
        public string $fromName,
        public string $content,
        public string $contentType,
        public ?string $mediaUrl = null,
        public ?string $caption = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $replyToMessageId = null,
        public ?array $rawPayload = null,
        public ?Carbon $timestamp = null,
    ) {}
}
```

### Built-in Drivers

| # | Provider | Driver Class | api_type | Auth Method |
|---|---------|-------------|----------|-------------|
| 1 | **Meta Cloud API** | `MetaOfficialDriver` | official | Bearer token + Phone Number ID |
| 2 | **Twilio** | `TwilioDriver` | official | Account SID + Auth Token |
| 3 | **WAsender** | `WasenderDriver` | unofficial | API Key + Instance ID |
| 4 | **UltraMsg** | `UltraMsgDriver` | unofficial | Instance ID + Token |
| 5 | **Maytapi** | `MaytapiDriver` | unofficial | Product ID + API Token |
| 6 | **Evolution API** | `EvolutionApiDriver` | unofficial | Server URL + Instance + API Key |
| 7 | **WPPConnect/120620** | `WppConnectDriver` | unofficial | Server URL + Session + Token |

### Custom HTTP Driver (No-Code)

For any new WhatsApp API provider without writing PHP code. Admin configures API mappings from UI.

```php
class CustomHttpDriver implements WhatsAppDriverInterface
{
    public function __construct(
        private WhatsappProvider $provider,
        private ?WhatsappProviderApiMapping $mapping = null,
    ) {}

    public function sendMessage(string $to, string $text, array $options = []): WhatsAppMessageResult
    {
        $mapping = $this->getMappingForAction('send_text');

        $headers = $this->interpolate($mapping->headers_template, [
            'api_key' => $this->provider->auth_config['api_key'] ?? '',
            'instance_id' => $this->provider->auth_config['instance_id'] ?? '',
            'token' => $this->provider->auth_config['token'] ?? '',
        ]);

        $body = $this->interpolate($mapping->body_template, [
            'to' => $to,
            'body' => $text,
        ]);

        $start = microtime(true);
        $response = Http::withHeaders($headers)
            ->{$mapping->method->value}(
                $this->provider->base_url . $mapping->endpoint,
                $body
            );
        $latency = (int)((microtime(true) - $start) * 1000);

        $messageId = data_get($response->json(), $mapping->response_mapping['message_id'] ?? 'data.id');

        return new WhatsAppMessageResult(
            success: $response->successful(),
            messageId: $messageId,
            rawResponse: $response->json(),
            latencyMs: $latency,
        );
    }

    private function interpolate(array $template, array $vars): array
    {
        $result = [];
        foreach ($template as $key => $value) {
            $result[$key] = str_replace(
                array_map(fn($k) => "{{$k}}", array_keys($vars)),
                array_values($vars),
                $value
            );
        }
        return $result;
    }
}
```

### WhatsAppService (Main Service)

```php
class WhatsAppService
{
    public function send(int $providerId, string $to, string $text, array $options = []): WhatsAppMessageResult
    {
        $provider = WhatsappProvider::findOrFail($providerId);
        $driver = $this->resolveDriver($provider);

        $result = $driver->sendMessage($to, $text, $options);

        WhatsappProviderLog::create([
            'provider_id' => $providerId,
            'direction' => 'outgoing',
            'phone' => $to,
            'payload' => ['text' => $text, 'options' => $options],
            'status' => $result->success ? 'sent' : 'failed',
            'error_message' => $result->errorMessage,
            'latency_ms' => $result->latencyMs,
        ]);

        return $result;
    }

    public function sendViaDefault(string $to, string $text, array $options = []): WhatsAppMessageResult
    {
        $provider = WhatsappProvider::where('is_default', true)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->send($provider->id, $to, $text, $options);
    }

    public function sendWithFallback(string $to, string $text, array $options = []): WhatsAppMessageResult
    {
        $providers = WhatsappProvider::where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->get();

        foreach ($providers as $provider) {
            try {
                $result = $this->send($provider->id, $to, $text, $options);
                if ($result->success) return $result;
            } catch (\Throwable $e) {
                continue;
            }
        }

        return new WhatsAppMessageResult(success: false, errorMessage: 'All providers failed');
    }

    private function resolveDriver(WhatsappProvider $provider): WhatsAppDriverInterface
    {
        if ($provider->driver_class && class_exists($provider->driver_class)) {
            return app($provider->driver_class, ['provider' => $provider]);
        }

        if ($provider->apiMappings()->exists()) {
            return new CustomHttpDriver($provider);
        }

        throw new \Exception("No driver found for provider: {$provider->name}");
    }
}
```

### Webhook Routing (Per-Provider)

```php
// routes/webhook.php
Route::post('/webhook/whatsapp/{provider:slug}', [WhatsAppWebhookController::class, 'handle']);
Route::get('/webhook/whatsapp/{provider:slug}', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhook/messenger', [MessengerWebhookController::class, 'handle']);
Route::get('/webhook/messenger', [MessengerWebhookController::class, 'verify']);

// Example URLs:
// /webhook/whatsapp/twilio       → TwilioDriver parses
// /webhook/whatsapp/wasender     → WasenderDriver parses
// /webhook/whatsapp/official     → MetaOfficialDriver parses
// /webhook/whatsapp/custom-flock → CustomHttpDriver parses
```

---

## Visual Workflow Builder (Vue Flow)

### Node Types

```
┌─────────────────────────────────────────────────────┐
│              Node Type Categories                    │
├─────────────┬───────────────────────────────────────┤
│ Trigger     │ • messageReceived                    │
│ Nodes       │ • keywordMatch                       │
│             │ • intentDetected                     │
│             │ • scheduleTrigger                    │
├─────────────┼───────────────────────────────────────┤
│ AI Nodes    │ • aiGenerateReply                    │
│             │ • aiDetectIntent                     │
│             │ • aiExtractEntities                  │
│             │ • aiSentimentAnalysis                │
├─────────────┼───────────────────────────────────────┤
│ Action      │ • sendMessage                        │
│ Nodes       │ • sendImage / sendFile               │
│             │ • sendQuickReplyButtons               │
│             │ • sendProductCard                    │
│             │ • sendPaymentLink                    │
│             │ • searchProducts                     │
│             │ • addToCart                          │
│             │ • removeFromCart                     │
│             │ • placeOrder                         │
│             │ • checkOrderStatus                   │
│             │ • checkPaymentStatus                 │
│             │ • createCustomer                     │
│             │ • updateCustomer                     │
│             │ • requestReturn                       │
├─────────────┼───────────────────────────────────────┤
│ Logic       │ • condition (if/else)                │
│ Nodes       │ • switch (multi-branch)              │
│             │ • delay / wait                       │
│             │ • loop                               │
│             │ • setVariable                        │
│             │ • getVariable                        │
├─────────────┼───────────────────────────────────────┤
│ Human       │ • humanHandoff                       │
│ Nodes       │ • assignToAgent                      │
│             │ • start50sTimer                       │
│             │ • check50sTimer                      │
│             │ • tagConversation                    │
├─────────────┼───────────────────────────────────────┤
│ Integration │ • whatsappSend                       │
│ Nodes       │ • messengerSend                      │
│             │ • webhookCall                         │
│             │ • httpRequest                         │
│             │ • sendEmail                           │
└─────────────┴───────────────────────────────────────┘
```

### Workflow Executor Engine

```php
class WorkflowExecutorService
{
    public function execute(
        ChatbotWorkflow $workflow,
        Conversation $conversation,
        ConversationMessage $triggerMessage
    ): ChatbotWorkflowExecution {
        $nodes = collect($workflow->nodes)->keyBy('id');
        $edges = collect($workflow->edges);

        $execution = ChatbotWorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'conversation_id' => $conversation->id,
            'message_id' => $triggerMessage->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $startNode = $nodes->first(fn($n) => in_array($n['type'], [
            'messageReceived', 'keywordMatch', 'intentDetected', 'scheduleTrigger'
        ]));

        $this->traverse($startNode, $nodes, $edges, $conversation, $execution);

        $execution->update(['status' => 'completed', 'completed_at' => now()]);
        return $execution;
    }

    private function traverse(
        array $currentNode,
        Collection $nodes,
        Collection $edges,
        Conversation $conversation,
        ChatbotWorkflowExecution $execution
    ): void {
        $executor = $this->resolveExecutor($currentNode['type']);
        $result = $executor->execute($currentNode, $conversation);

        $executedNodes = $execution->executed_nodes ?? [];
        $executedNodes[] = [
            'nodeId' => $currentNode['id'],
            'status' => 'completed',
            'output' => $result,
        ];
        $execution->update(['executed_nodes' => $executedNodes]);

        $outgoingEdges = $edges->where('source', $currentNode['id']);

        foreach ($outgoingEdges as $edge) {
            if ($edge['condition'] ?? null) {
                if (!$this->evaluateCondition($edge['condition'], $result)) {
                    continue;
                }
            }

            $nextNode = $nodes[$edge['target']];
            $this->traverse($nextNode, $nodes, $edges, $conversation, $execution);
        }
    }

    private function resolveExecutor(string $nodeType): NodeExecutorInterface
    {
        return match ($nodeType) {
            'messageReceived' => new Nodes\Trigger\MessageReceivedNode(),
            'keywordMatch' => new Nodes\Trigger\KeywordMatchNode(),
            'intentDetected' => new Nodes\Trigger\IntentDetectedNode(),
            'scheduleTrigger' => new Nodes\Trigger\ScheduleTriggerNode(),
            'aiGenerateReply' => new Nodes\AI\GenerateReplyNode(),
            'aiDetectIntent' => new Nodes\AI\DetectIntentNode(),
            'aiExtractEntities' => new Nodes\AI\ExtractEntitiesNode(),
            'aiSentimentAnalysis' => new Nodes\AI\SentimentAnalysisNode(),
            'sendMessage' => new Nodes\Action\SendMessageNode(),
            'sendImage' => new Nodes\Action\SendImageNode(),
            'sendQuickReplyButtons' => new Nodes\Action\SendQuickReplyButtonsNode(),
            'sendProductCard' => new Nodes\Action\SendProductCardNode(),
            'sendPaymentLink' => new Nodes\Action\SendPaymentLinkNode(),
            'searchProducts' => new Nodes\Action\SearchProductsNode(),
            'addToCart' => new Nodes\Action\AddToCartNode(),
            'removeFromCart' => new Nodes\Action\RemoveFromCartNode(),
            'placeOrder' => new Nodes\Action\PlaceOrderNode(),
            'checkOrderStatus' => new Nodes\Action\CheckOrderStatusNode(),
            'checkPaymentStatus' => new Nodes\Action\CheckPaymentStatusNode(),
            'createCustomer' => new Nodes\Action\CreateCustomerNode(),
            'updateCustomer' => new Nodes\Action\UpdateCustomerNode(),
            'requestReturn' => new Nodes\Action\RequestReturnNode(),
            'condition' => new Nodes\Logic\ConditionNode(),
            'switch' => new Nodes\Logic\SwitchNode(),
            'delay' => new Nodes\Logic\DelayNode(),
            'loop' => new Nodes\Logic\LoopNode(),
            'setVariable' => new Nodes\Logic\SetVariableNode(),
            'getVariable' => new Nodes\Logic\GetVariableNode(),
            'humanHandoff' => new Nodes\Human\HandoffNode(),
            'assignToAgent' => new Nodes\Human\AssignToAgentNode(),
            'start50sTimer' => new Nodes\Human\StartTimerNode(),
            'check50sTimer' => new Nodes\Human\CheckTimerNode(),
            'tagConversation' => new Nodes\Human\TagConversationNode(),
            'whatsappSend' => new Nodes\Integration\WhatsAppSendNode(),
            'messengerSend' => new Nodes\Integration\MessengerSendNode(),
            'webhookCall' => new Nodes\Integration\WebhookCallNode(),
            'httpRequest' => new Nodes\Integration\HttpRequestNode(),
            'sendEmail' => new Nodes\Integration\SendEmailNode(),
            default => throw new \Exception("Unknown node type: {$nodeType}"),
        };
    }
}
```

### Node Executor Interface

```php
interface NodeExecutorInterface
{
    public function execute(array $node, Conversation $conversation): array;
}
```

### Example Node Implementations

```php
class SendMessageNode implements NodeExecutorInterface
{
    public function execute(array $node, Conversation $conversation): array
    {
        $message = $node['data']['message'] ?? '';
        $message = $this->replaceVariables($message, $conversation);

        app(ConversationService::class)->sendToChannel($conversation, $message);

        return ['sent' => true, 'message' => $message];
    }
}

class ConditionNode implements NodeExecutorInterface
{
    public function execute(array $node, Conversation $conversation): array
    {
        $field = $node['data']['field'];
        $operator = $node['data']['operator'];
        $value = $node['data']['value'];

        $actualValue = $this->getContextValue($field, $conversation);
        $result = $this->compare($actualValue, $operator, $value);

        return ['condition_met' => $result, 'branch' => $result ? 'yes' : 'no'];
    }
}

class SearchProductsNode implements NodeExecutorInterface
{
    public function execute(array $node, Conversation $conversation): array
    {
        $query = $node['data']['query'] ?? '';
        $query = $this->replaceVariables($query, $conversation);

        $products = Product::where('name', 'like', "%{$query}%")
            ->orWhere('name_chinese', 'like', "%{$query}%")
            ->orWhere('sku', 'like', "%{$query}%")
            ->with(['category', 'stockItems'])
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->wholesalePrice(),
                'stock' => $p->totalStock(),
                'sku' => $p->sku,
            ]);

        return ['products' => $products->toArray(), 'count' => $products->count()];
    }
}

class PlaceOrderNode implements NodeExecutorInterface
{
    public function execute(array $node, Conversation $conversation): array
    {
        $cart = ChatCart::where('conversation_id', $conversation->id)
            ->where('status', 'active')
            ->firstOrFail();

        $paymentType = $node['data']['payment_type'] ?? 'cod';

        $order = app(WholesaleOrderService::class)->createFromCart($cart, $paymentType);

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total_bdt' => $order->total_bdt,
            'status' => $order->status,
        ];
    }
}
```

### Pre-built Workflow Templates

| Template | Description |
|----------|-------------|
| Greeting Flow | New customer greeting + quick replies |
| Order Collection | Product search → cart → order → payment |
| Order Status Check | Order number → status reply |
| Payment Follow-up | Unpaid order → payment link / reminder |
| Return Request | Return request → human handoff |
| Out of Stock Alert | No stock → alternative suggestion |
| VIP Customer Flow | VIP customer → direct human handoff |
| Wholesale Inquiry | Wholesale price → tier → bulk order |

---

## AI Agent (Laravel 13 AI SDK)

### Fallback Agent (when no workflow matches)

```php
class AgentReplyService
{
    public function generateAndSend(int $conversationId, string $channelMessageId): void
    {
        $conversation = Conversation::with('messages')->find($conversationId);
        $lastMessage = ConversationMessage::where('conversation_id', $conversationId)
            ->where('message_id', $channelMessageId)
            ->first();

        // Using official laravel/ai SDK (composer require laravel/ai)
        $agent = AI::agent(ZamZamChatAgent::class)
            ->withInstructions($this->getSystemPrompt($conversation))
            ->withHistory($this->buildChatHistory($conversation))
            ->withTools([
                new ProductSearchTool(),
                new AddToCartTool(),
                new RemoveFromCartTool(),
                new PlaceOrderTool(),
                new CheckOrderStatusTool(),
                new CheckPaymentStatusTool(),
                new SendPaymentLinkTool(),
                new CheckStockTool(),
                new CreateCustomerTool(),
                new RequestReturnTool(),
            ]);

        $response = $agent->chat($lastMessage->content);

        foreach ($response->toolResults as $toolResult) {
            AgentAction::create([
                'conversation_id' => $conversationId,
                'message_id' => $lastMessage->id,
                'action_type' => $toolResult->toolName,
                'action_data' => $toolResult->arguments,
                'action_result' => $toolResult->result,
                'status' => 'executed',
            ]);
        }

        $this->sendReply($conversation, $response->text);

        app(HumanFirstTimerService::class)->aiReplied($conversationId);
    }

    private function getSystemPrompt(Conversation $conversation): string
    {
        $customer = $conversation->customer;
        return "You are ZamZam Trading's AI assistant for {$conversation->channel} channel.

Customer: {$customer?->name ?? 'New customer'}
Language: Detect from customer message. Reply in same language (Bengali/English/Chinese).

Rules:
- You can search products, check stock, check prices
- You can add/remove items from cart
- You can place orders for the customer
- You can check order status and payment status
- You can send payment links
- For returns, create a return request (needs human approval)
- Be friendly, helpful, and concise
- If unsure, say you'll connect to a human agent
- Prices are in BDT (Bangladeshi Taka)
- Always confirm before placing an order";
    }
}
```

### AI Tool Calling Examples (laravel/ai SDK)

Tools are created using `php artisan make:tool ToolName` and implement `Laravel\Ai\Contracts\Tool`.

```php
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Attributes\{ToolName, ToolDescription, Parameter};

#[ToolName('product_search')]
#[ToolDescription('Search products by name, category, or SKU')]
class ProductSearchTool implements Tool
{
    public function handle(
        #[Parameter('Search term')] string $query,
        #[Parameter('Category name (optional)')] ?string $category = null
    ): string {
        $products = Product::where('name', 'like', "%{$query}%")
            ->orWhere('name_chinese', 'like', "%{$query}%")
            ->orWhere('sku', 'like', "%{$query}%")
            ->when($category, fn($q) => $q->whereHas('category', fn($cq) => $cq->where('name', $category)))
            ->with(['category', 'stockItems'])
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'name' => $p->name,
                'price_bdt' => $p->wholesalePrice(),
                'stock' => $p->totalStock(),
                'sku' => $p->sku,
            ]);

        return json_encode($products);
    }
}

#[ToolName('place_order')]
#[ToolDescription('Convert active chat cart to a sales order')]
class PlaceOrderTool implements Tool
{
    public function handle(
        #[Parameter('Cart ID')] int $cart_id,
        #[Parameter('Payment type: cash, credit, or cod')] string $payment_type
    ): string {
        $cart = ChatCart::findOrFail($cart_id);
        $order = app(WholesaleOrderService::class)->createFromCart($cart, $payment_type);

        return json_encode([
            'order_number' => $order->order_number,
            'total_bdt' => $order->total_bdt,
            'status' => $order->status,
        ]);
    }
}
```

---

## Real-Time Chat Dashboard (Laravel Reverb)

### Frontend

```javascript
// Vue Component - ChatDashboard.vue
import Echo from 'laravel-echo';

const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_KEY,
    cluster: import.meta.env.VITE_REVERB_CLUSTER,
});

echo.private(`conversation.${conversationId}`)
    .listen('NewMessage', (event) => {
        messages.value.push(event.message);
        scrollBottom();
    })
    .listen('AgentAction', (event) => {
        agentActions.value.push(event.action);
    })
    .listen('AiStatusChanged', (event) => {
        isAiActive.value = event.isActive;
    });
```

### Dashboard Layout

```
┌──────────────────────────────────────────────────────────────┐
│  Conversations │  Chat Thread          │ Customer Info        │
│  ┌──────────┐  │  ┌────────────────┐  │ ┌──────────────────┐│
│  │ 🔴 Active │  │  │ 👤 মগ কত?     │  │ │ Rahim Store      ││
│  │ Rahim    │  │  │ 🤖 100টা মগ... │  │ │ 📱 01XX...       ││
│  │ Karim    │  │  │ 👤 কার্টে যোগ  │  │ │ 💰 ৳1.2L due     ││
│  │ 🟢 Idle  │  │  │ 🤖 যোগ হয়েছে  │  │ │ 📦 3 orders      ││
│  │ Ali      │  │  │ 👤 অর্ডার কর  │  │ ├──────────────────┤│
│  │ Fatema   │  │  │ 🤖 Order #...   │  │ │ 🛒 Cart:         ││
│  └──────────┘  │  │                  │  │ │ 100 মগ          ││
│                │  │  ┌──────────────┐│  │ │ ৳XX,XXX         ││
│                │  │  │ Type reply.. ││  │ ├──────────────────┤│
│                │  │  └──────────────┘│  │ │ Agent Log:       ││
│                │  └────────────────┘  │ │ ✅ add_cart       ││
│                │                      │ │ ✅ place_order    ││
│                │                      │ └──────────────────┘│
└──────────────────────────────────────────────────────────────┘
```

---

## Database Tables

### conversations
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| conversation_uuid | varchar(50) unique | UUID for external reference |
| customer_id | bigint FK nullable customers.id | Matched customer |
| channel | enum | messenger, whatsapp |
| whatsapp_provider_id | bigint FK nullable whatsapp_providers.id | Which WA provider |
| channel_conversation_id | varchar(255) | Messenger thread ID / WhatsApp chat ID |
| channel_customer_id | varchar(255) | Messenger PSID / WhatsApp phone |
| channel_customer_name | varchar(255) nullable | Name from channel |
| channel_customer_avatar | varchar(500) nullable | Profile picture |
| status | enum | active, idle, closed |
| assigned_to | bigint FK nullable users.id | Human agent |
| is_ai_active | boolean default true | AI currently replying? |
| last_message_at | timestamp nullable | |
| last_human_reply_at | timestamp nullable | |
| last_ai_reply_at | timestamp nullable | |
| active_workflow_id | bigint FK nullable chatbot_workflows.id | Running workflow |
| tags | json nullable | ["vip", "complaint", "wholesale"] |
| ai_context | json nullable | AI context/memory |
| metadata | json nullable | Extra channel data |
| created_at | timestamp | |
| updated_at | timestamp | |

### conversation_messages
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| conversation_id | bigint FK conversations.id | |
| message_id | varchar(255) unique | Channel message ID |
| sender_type | enum | customer, ai_agent, human_agent, system |
| sender_id | bigint FK nullable users.id | Human agent user ID |
| content | text | Message content |
| content_type | enum | text, image, file, product_card, order_card, payment_link, quick_reply, location, audio, video, sticker |
| attachments | json nullable | [{type, url, caption}] |
| intent_detected | varchar(100) nullable | order_inquiry, product_search, place_order, check_status, complaint, greeting, other |
| confidence_score | decimal(5,2) nullable | AI confidence 0-100 |
| replied_within_50s | boolean nullable | Human replied within 50s? |
| is_read | boolean default false | |
| read_at | timestamp nullable | |
| metadata | json nullable | |
| created_at | timestamp | |

### chat_carts
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| conversation_id | bigint FK conversations.id | |
| customer_id | bigint FK nullable customers.id | |
| status | enum | active, converted_to_order, abandoned, expired |
| total_bdt | decimal(14,2) default 0 | |
| notes | text nullable | |
| converted_order_id | bigint FK nullable sales_orders.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### chat_cart_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| chat_cart_id | bigint FK chat_carts.id | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| qty | int | |
| price_bdt | decimal(12,2) | Price at time of adding |
| subtotal_bdt | decimal(14,2) | |
| added_by | enum | ai_agent, human_agent, customer |
| notes | text nullable | |
| created_at | timestamp | |

### agent_actions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| conversation_id | bigint FK conversations.id | |
| message_id | bigint FK nullable conversation_messages.id | |
| action_type | enum | product_search, add_to_cart, remove_from_cart, clear_cart, place_order, check_order_status, check_payment_status, send_payment_link, check_stock, get_price, create_customer, update_customer, send_return_request |
| action_data | json nullable | Action parameters |
| action_result | json nullable | Action result |
| status | enum | pending, executed, failed, requires_approval |
| approved_by | bigint FK nullable users.id | |
| approved_at | timestamp nullable | |
| executed_at | timestamp nullable | |
| error_message | text nullable | |
| created_at | timestamp | |

### conversation_tags
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(100) unique | vip, complaint, wholesale, follow_up |
| color | varchar(7) nullable | #FF5733 |
| is_active | boolean default true | |
| created_at | timestamp | |

### quick_reply_templates
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | |
| content | text | Template message |
| category | varchar(100) nullable | greeting, product_info, order_status, payment |
| language | varchar(5) | bn, en, zh |
| is_active | boolean default true | |
| created_at | timestamp | |

### whatsapp_providers
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(100) | "Twilio", "WAsender", "UltraMsg" |
| slug | varchar(100) unique | "twilio", "wasender", "ultramsg" |
| driver_class | varchar(255) nullable | App\Services\WhatsApp\Drivers\TwilioDriver |
| api_type | enum | official, unofficial, hybrid |
| base_url | varchar(500) nullable | API endpoint |
| auth_config | json | {api_key, api_secret, instance_id, ...} |
| webhook_config | json nullable | {verify_token, webhook_path} |
| capabilities | json | {send_text, send_media, send_template, send_buttons, groups, bulk, read_receipts} |
| rate_limits | json nullable | {messages_per_minute: 60} |
| is_active | boolean default true | |
| is_default | boolean default false | Default provider? |
| priority | int default 0 | Higher = first choice |
| phone_number | varchar(20) nullable | Connected number |
| phone_number_id | varchar(100) nullable | Meta Cloud API phone_number_id |
| business_account_id | varchar(100) nullable | Meta business account |
| last_error | text nullable | |
| last_connected_at | timestamp nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### whatsapp_provider_api_mappings
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| provider_id | bigint FK whatsapp_providers.id | |
| action | enum | send_text, send_media, send_template, send_buttons, send_list, send_location, mark_read, check_number |
| method | enum | POST, GET, PUT |
| endpoint | varchar(500) | /messages/send |
| headers_template | json | {"Authorization": "Bearer {{api_key}}"} |
| body_template | json | {"number": "{{to}}", "text": "{{body}}"} |
| response_mapping | json | {"message_id": "data.id", "status": "data.status"} |
| error_mapping | json nullable | {"code": "error.code", "message": "error.message"} |
| created_at | timestamp | |
| updated_at | timestamp | |

### whatsapp_provider_logs
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| provider_id | bigint FK whatsapp_providers.id | |
| direction | enum | incoming, outgoing |
| message_id | varchar(255) nullable | |
| conversation_id | bigint FK nullable conversations.id | |
| phone | varchar(20) | |
| payload | json | Full request/response |
| status | enum | sent, delivered, read, failed, rate_limited |
| error_message | text nullable | |
| latency_ms | int nullable | |
| created_at | timestamp | |

### chatbot_workflows
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | "Greeting Flow", "Order Collection" |
| description | text nullable | |
| trigger_type | enum | incoming_message, keyword, intent, schedule, event |
| trigger_config | json nullable | {keyword: "হ্যালো", intent: "greeting"} |
| nodes | json | [{id, type, position, data, config}] |
| edges | json | [{id, source, target, sourceHandle, condition}] |
| status | enum | active, draft, archived |
| version | int default 1 | Versioning |
| is_default | boolean default false | Default greeting flow? |
| channel | enum nullable | all, whatsapp, messenger |
| priority | int default 0 | Higher priority wins when multiple match |
| execution_count | int default 0 | Times executed |
| last_executed_at | timestamp nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### chatbot_workflow_executions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| workflow_id | bigint FK chatbot_workflows.id | |
| conversation_id | bigint FK conversations.id | |
| message_id | bigint FK nullable conversation_messages.id | |
| executed_nodes | json | [{nodeId, status, output, duration_ms}] |
| status | enum | running, completed, failed, paused |
| error_message | text nullable | |
| started_at | timestamp | |
| completed_at | timestamp nullable | |
| created_at | timestamp | |

---

## API Routes

### Webhook Routes (External → ERP)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /webhook/whatsapp/{provider:slug} | WhatsApp webhook verification |
| POST | /webhook/whatsapp/{provider:slug} | WhatsApp incoming message |
| GET | /webhook/messenger | Messenger webhook verification |
| POST | /webhook/messenger | Messenger incoming message |

### Internal Chat API (ERP Dashboard)

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/chat/conversations | Conversation list | chat.view |
| GET | /api/chat/conversations/{id} | Conversation detail + messages | chat.view |
| POST | /api/chat/conversations/{id}/reply | Human reply | chat.reply |
| PATCH | /api/chat/conversations/{id}/assign | Assign human agent | chat.manage |
| PATCH | /api/chat/conversations/{id}/ai-toggle | Toggle AI on/off | chat.manage |
| GET | /api/chat/conversations/{id}/cart | View cart | chat.view |
| POST | /api/chat/conversations/{id}/cart/items | Add item to cart | chat.manage |
| DELETE | /api/chat/conversations/{id}/cart/items/{itemId} | Remove from cart | chat.manage |
| POST | /api/chat/conversations/{id}/tags | Add tag | chat.manage |
| GET | /api/chat/agent-actions | Agent action list | chat.view |
| POST | /api/chat/agent-actions/{id}/approve | Approve action | chat.manage |
| GET | /api/chat/dashboard | Chat dashboard stats | chat.view |

### WhatsApp Provider Management API

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/whatsapp/providers | Provider list | chat.manage |
| POST | /api/whatsapp/providers | Add provider | chat.manage |
| GET | /api/whatsapp/providers/{id} | Provider detail | chat.manage |
| PUT | /api/whatsapp/providers/{id} | Update provider | chat.manage |
| DELETE | /api/whatsapp/providers/{id} | Delete provider | chat.manage |
| POST | /api/whatsapp/providers/{id}/test | Test connection | chat.manage |
| GET | /api/whatsapp/providers/{id}/logs | Provider logs | chat.view |
| GET | /api/whatsapp/providers/{id}/health | Health status | chat.view |

### Workflow Builder API

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/chatbot/workflows | Workflow list | chat.manage |
| POST | /api/chatbot/workflows | Create workflow | chat.manage |
| GET | /api/chatbot/workflows/{id} | Workflow detail | chat.manage |
| PUT | /api/chatbot/workflows/{id} | Update workflow | chat.manage |
| DELETE | /api/chatbot/workflows/{id} | Delete workflow | chat.manage |
| POST | /api/chatbot/workflows/{id}/duplicate | Duplicate workflow | chat.manage |
| POST | /api/chatbot/workflows/{id}/test | Test workflow | chat.manage |
| GET | /api/chatbot/workflows/{id}/executions | Execution history | chat.view |
| GET | /api/chatbot/workflows/templates | List templates | chat.view |
| POST | /api/chatbot/workflows/from-template | Create from template | chat.manage |
| GET | /api/chatbot/node-types | Available node types | chat.manage |

### Quick Reply Templates API

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/chat/quick-replies | Template list | chat.view |
| POST | /api/chat/quick-replies | Create template | chat.manage |
| PUT | /api/chat/quick-replies/{id} | Update template | chat.manage |
| DELETE | /api/chat/quick-replies/{id} | Delete template | chat.manage |

---

## Frontend Pages

```
💬 Conversations (Main Menu)
  ├── Inbox (Unassigned + My Chats)
  ├── All Conversations
  ├── Conversation Detail
  │   ├── Message Thread (Live via Reverb)
  │   ├── Customer Info Panel
  │   ├── Cart Preview
  │   ├── Agent Action Log
  │   └── Order History
  ├── Quick Reply Templates
  ├── Conversation Tags
  └── Agent Action Logs

🤖 Chatbot Builder (Main Menu)
  ├── Workflows List
  ├── Workflow Editor (Vue Flow drag-drop)
  │   ├── Node Palette (left sidebar)
  │   ├── Canvas (center)
  │   └── Node Config Panel (right sidebar)
  ├── Workflow Test Mode
  ├── Workflow Executions (History)
  └── Workflow Templates

⚙️ Settings > WhatsApp
  ├── Providers List
  ├── Add/Edit Provider
  ├── Provider API Mappings (Custom HTTP)
  ├── Provider Health Dashboard
  └── Provider Logs
```

---

## Permissions

| Permission | Description |
|-----------|-------------|
| chat.view | View conversations and messages |
| chat.reply | Send human replies |
| chat.manage | Manage conversations, assign agents, toggle AI |
| chatbot.manage | Create/edit/delete workflows |
| whatsapp.manage | Manage WhatsApp providers |

**Role assignments:**

| Role | Permissions |
|------|------------|
| Admin | All |
| Manager | chat.view, chat.reply, chat.manage, chatbot.manage, whatsapp.manage |
| Salesman | chat.view, chat.reply |

---

## File/Class Structure

```
app/
├── Models/
│   └── Chat/
│       ├── Conversation.php
│       ├── ConversationMessage.php
│       ├── ChatCart.php
│       ├── ChatCartItem.php
│       ├── AgentAction.php
│       ├── ConversationTag.php
│       ├── QuickReplyTemplate.php
│       ├── WhatsappProvider.php
│       ├── WhatsappProviderApiMapping.php
│       ├── WhatsappProviderLog.php
│       ├── ChatbotWorkflow.php
│       └── ChatbotWorkflowExecution.php
├── Http/Controllers/
│   ├── Webhook/
│   │   ├── WhatsAppWebhookController.php
│   │   └── MessengerWebhookController.php
│   ├── Web/
│   │   └── Chat/
│   │       ├── ConversationController.php
│   │       ├── ChatDashboardController.php
│   │       ├── QuickReplyController.php
│   │       └── ConversationTagController.php
│   └── Api/
│       └── Chat/
│           ├── ConversationApiController.php
│           ├── AgentActionController.php
│           ├── WhatsappProviderController.php
│           └── ChatbotWorkflowController.php
├── Services/
│   ├── Chat/
│   │   ├── ConversationService.php
│   │   ├── HumanFirstTimerService.php
│   │   ├── AgentReplyService.php
│   │   └── WorkflowExecutorService.php
│   └── WhatsApp/
│       ├── WhatsAppService.php
│       ├── WhatsAppDriverInterface.php
│       ├── WhatsAppMessageResult.php
│       ├── WhatsAppIncomingMessage.php
│       ├── CustomHttpDriver.php
│       └── Drivers/
│           ├── MetaOfficialDriver.php
│           ├── TwilioDriver.php
│           ├── WasenderDriver.php
│           ├── UltraMsgDriver.php
│           ├── MaytapiDriver.php
│           ├── EvolutionApiDriver.php
│           └── WppConnectDriver.php
├── AI/
│   └── Tools/
│       ├── ProductSearchTool.php
│       ├── AddToCartTool.php
│       ├── RemoveFromCartTool.php
│       ├── PlaceOrderTool.php
│       ├── CheckOrderStatusTool.php
│       ├── CheckPaymentStatusTool.php
│       ├── SendPaymentLinkTool.php
│       ├── CheckStockTool.php
│       ├── CreateCustomerTool.php
│       └── RequestReturnTool.php
├── Workflow/
│   ├── NodeExecutorInterface.php
│   └── Nodes/
│       ├── Trigger/
│       │   ├── MessageReceivedNode.php
│       │   ├── KeywordMatchNode.php
│       │   ├── IntentDetectedNode.php
│       │   └── ScheduleTriggerNode.php
│       ├── AI/
│       │   ├── GenerateReplyNode.php
│       │   ├── DetectIntentNode.php
│       │   ├── ExtractEntitiesNode.php
│       │   └── SentimentAnalysisNode.php
│       ├── Action/
│       │   ├── SendMessageNode.php
│       │   ├── SendImageNode.php
│       │   ├── SendQuickReplyButtonsNode.php
│       │   ├── SendProductCardNode.php
│       │   ├── SendPaymentLinkNode.php
│       │   ├── SearchProductsNode.php
│       │   ├── AddToCartNode.php
│       │   ├── RemoveFromCartNode.php
│       │   ├── PlaceOrderNode.php
│       │   ├── CheckOrderStatusNode.php
│       │   ├── CheckPaymentStatusNode.php
│       │   ├── CreateCustomerNode.php
│       │   ├── UpdateCustomerNode.php
│       │   └── RequestReturnNode.php
│       ├── Logic/
│       │   ├── ConditionNode.php
│       │   ├── SwitchNode.php
│       │   ├── DelayNode.php
│       │   ├── LoopNode.php
│       │   ├── SetVariableNode.php
│       │   └── GetVariableNode.php
│       ├── Human/
│       │   ├── HandoffNode.php
│       │   ├── AssignToAgentNode.php
│       │   ├── StartTimerNode.php
│       │   ├── CheckTimerNode.php
│       │   └── TagConversationNode.php
│       └── Integration/
│           ├── WhatsAppSendNode.php
│           ├── MessengerSendNode.php
│           ├── WebhookCallNode.php
│           ├── HttpRequestNode.php
│           └── SendEmailNode.php
├── Jobs/
│   ├── ProcessIncomingMessageJob.php
│   ├── HumanFirstTimeoutJob.php
│   ├── ExecuteWorkflowJob.php
│   └── SendChannelMessageJob.php
├── Events/
│   ├── NewMessage.php
│   ├── AiStatusChanged.php
│   └── AgentActionPerformed.php
└── Policies/
    ├── ConversationPolicy.php
    └── ChatbotWorkflowPolicy.php

resources/js/
├── Pages/
│   └── Chat/
│       ├── Inbox.vue
│       ├── Conversations.vue
│       ├── ConversationDetail.vue
│       ├── QuickReplies.vue
│       ├── Tags.vue
│       └── AgentActions.vue
├── Pages/
│   └── Chatbot/
│       ├── Workflows.vue
│       ├── WorkflowEditor.vue
│       ├── WorkflowTest.vue
│       └── Executions.vue
├── Components/
│   └── Chat/
│       ├── MessageBubble.vue
│       ├── CustomerInfoPanel.vue
│       ├── CartPreview.vue
│       ├── AgentActionLog.vue
│       ├── WorkflowNode/
│       │   ├── TriggerNode.vue
│       │   ├── AINode.vue
│       │   ├── ActionNode.vue
│       │   ├── LogicNode.vue
│       │   ├── HumanNode.vue
│       │   └── IntegrationNode.vue
│       └── NodeConfigPanel.vue
└── Composables/
    ├── useChat.ts
    ├── useWebSocket.ts
    └── useWorkflow.ts
```

---

## Example Flow: Order via Chat

```
Customer (WhatsApp): "আমাকে ১০০টা মগ দাম দিন"
      ↓
[Webhook → ProcessIncomingMessageJob]
      ↓
[50s Timer Starts → HumanFirstTimerService]
      ↓
[Match Workflow: "Order Collection" (keyword/intent match)]
      ↓
[WorkflowExecutorService traverses nodes]
  Node 1: messageReceived → ✓
  Node 2: aiDetectIntent → intent: "product_inquiry", entity: মগ, qty: 100
  Node 3: searchProducts → found: মগ (SKU-MUG-001), price: ৳45/pc, stock: 500
  Node 4: condition → has stock? → yes
  Node 5: sendMessage → "100টা মগের দাম ৳৪,৫০০ (৳৪৫/পিস)। স্টকে আছে। কার্টে যোগ করবেন?"
      ↓
Customer: "হ্যাঁ কার্টে যোগ কর"
      ↓
[Workflow continues]
  Node: addToCart → ChatCart + ChatCartItem created
  Node: sendMessage → "কার্টে যোগ হয়েছে! মোট: ৳৪,৫০০। আরো কিছু? নাকি অর্ডার করবেন?"
      ↓
Customer: "অর্ডার কর"
      ↓
[Workflow continues]
  Node: placeOrder → Sales Order #SO-2026-0001 created
  Node: condition → payment type? → COD (default)
  Node: sendMessage → "অর্ডার হয়েছে! ✅\nঅর্ডার: #SO-2026-0001\nমোট: ৳৪,৫০০\nপেমেন্ট: ক্যাশ অন ডেলিভারি"
  Node: AgentAction logged → place_order, executed
```

---

## UI/UX Design

### Chat Inbox (Intercom/Crisp-style)

```
┌─────────────────────────────────────────────────────────────────────────┐
│ 💬 Conversations                          🔍 Search...    🔔 3  ⚙️     │
├──────────────┬──────────────────────────────────────────────────────────┤
│              │                                                          │
│  FILTERS     │  Rahim Store 🟢                                          │
│  ┌────────┐  │  WhatsApp • Last seen: 2 min ago                        │
│  │All     │  │  ────────────────────────────────────────────────────── │
│  │Unread  │  │                                                          │
│  │Mine    │  │  ┌──────────────────────────────────────────┐           │
│  │Unassis.│  │  │  👤 ভাই মগ এর দাম কত?                    │  10:32 AM│
│  │AI Active│  │  │                                            │           │
│  └────────┘  │  │       🤖 100টা মগের দাম ৳৪,৫০০/পিস     │  10:33 AM│
│              │  │          স্টকে আছে (500 pcs)              │           │
│  🔴 ACTIVE   │  │          [কার্টে যোগ] [বিস্তারিত]       │           │
│  ┌────────┐  │  └──────────────────────────────────────────┘           │
│  │🟢 Rahim│  │                                                          │
│  │  "মগ এর│  │  ┌──────────────────────────────────────────┐           │
│  │   দাম.."│  │  │  👤 কার্টে যোগ কর                           │  10:34 AM│
│  │         │  │  └──────────────────────────────────────────┘           │
│  │🟢 Karim│  │                                                          │
│  │  "অর্ডার│  │  ┌──────────────────────────────────────────┐           │
│  │   স্ট্যাট│  │  │       🤖 কার্টে যোগ হয়েছে! ✅           │  10:34 AM│
│  │   স.."  │  │  │          মোট: ৳৪,৫০০                     │           │
│  │         │  │  │          অর্ডার করবেন? আরো চান?           │           │
│  │🟡 Ali  │  │  └──────────────────────────────────────────┘           │
│  │  "রিটার্│  │                                                          │
│  │   ন.."  │  │  ─── Typing... ───                                       │
│  └────────┘  │                                                          │
│              │  ┌──────────────────────────────────────────────────────┐ │
│  🟡 IDLE     │  │  ⚡ AI is active  [🤖 Pause AI] [👤 Takeover]       │ │
│  ┌────────┐  │  ├──────────────────────────────────────────────────────┤ │
│  │  Fatema│  │  │  💬 Type a message...              📎 😊 📤        │ │
│  │  "ধন্যবাদ│  │  │                                    ▶ Send      │ │
│  └────────┘  │  └──────────────────────────────────────────────────────┘ │
│              ├──────────────────────────────────────────────────────────┤
│  ✅ CLOSED   │  📋 Customer  │  🛒 Cart  │  📊 Actions  │  📦 Orders   │
│  ┌────────┐  │                                                          │
│  │  Hassan│  │  ┌──────────────────────────────────────────────┐       │
│  │  "বিদায়"│  │  │  Rahim Store                                │       │
│  └────────┘  │  │  📱 +880 1XXX-XXXXXX                        │       │
│              │  │  🏪 Wholesale • Bronze Tier                   │       │
│              │  │  💰 Outstanding: ৳1,20,000                   │       │
│              │  │  📊 Total Orders: 45  │  🕐 Since: Jan 2025 │       │
│              │  │  ──────────────────────────────────────────── │       │
│              │  │  🏷️ Tags: [+ Add]  [VIP] [Wholesale]        │       │
│              │  └──────────────────────────────────────────────┘       │
└──────────────┴──────────────────────────────────────────────────────────┘
```

### Chat Color Coding

| Element | Color | Hex | Reason |
|---------|-------|-----|--------|
| AI message bubble | Light purple | `#F3E8FF` + 🤖 | Distinguish AI from human |
| Human agent bubble | Light blue | `#DBEAFE` + 👤 | Agent reply |
| Customer bubble | White/gray | `#F9FAFB` | Incoming |
| 50s timer bar | Green→Yellow→Red | gradient | Timer countdown |
| AI Active badge | Purple pulse | `#8B5CF6` | AI is on |
| Human Active badge | Green dot | `#10B981` | Human chatting |
| Urgent conversation | Red border glow | `#EF4444` | Needs fast reply |
| VIP tag | Gold | `#F59E0B` | VIP customer |

### 50-Second Timer Visual

```
50s remaining (green):
┌─────────────────────────────────────────┐
│ ⏱️ 50s  ██████████████████████████ 100% │
│    Waiting for human reply...           │
└─────────────────────────────────────────┘

25s remaining (yellow):
┌─────────────────────────────────────────┐
│ ⏱️ 25s  ██████████████░░░░░░░░░░░░ 50% │
│    ⚠️ AI will reply in 25 seconds       │
└─────────────────────────────────────────┘

5s remaining (red, pulsing):
┌─────────────────────────────────────────┐
│ ⏱️ 5s   ███░░░░░░░░░░░░░░░░░░░░░ 10%  │
│    🔴 AI taking over in 5 seconds!       │
└─────────────────────────────────────────┘

AI replied:
┌─────────────────────────────────────────┐
│ 🤖 AI replied • 50s timer expired       │
│    [👤 Take Over] [🤖 Let AI Continue]   │
└─────────────────────────────────────────┘
```

### Chat Dashboard Stats (Header Cards)

```
┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌────────────┐
│ 🔴 Active    │  │ ⏱️ Waiting   │  │ 🤖 AI Active │  │ ✅ Resolved│
│              │  │   >50s       │  │              │  │   Today    │
│     12       │  │     3        │  │     8        │  │    27      │
│   chats      │  │   chats      │  │  chats       │  │  chats     │
└──────────────┘  └──────────────┘  └──────────────┘  └────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│  Avg Response Time: 32s  │  AI Replies Today: 156  │  Human: 89   │
└──────────────────────────────────────────────────────────────────────┘
```

### Workflow Builder (n8n/Make-style)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ← Back to Workflows    "Order Collection Flow"  v2   [Draft ▼] [💾 Save]│
│                                                    [▶ Test] [📋 Clone]   │
├────────┬────────────────────────────────────────────────────┬─────────────┤
│        │                                                    │             │
│ NODES  │                                                    │  NODE       │
│        │    ┌──────────────┐                                │  CONFIG     │
│ 🔵 Trig│    │  📩 Message  │                                │             │
│  │Msg  │    │   Received   │                                │  🤖 AI     │
│  │Key  │    └──────┬───────┘                                │  Generate  │
│  │Intnt│           │                                        │  Reply     │
│  │Schdl│           ▼                                        │             │
│        │    ┌──────────────┐                                │  Model:    │
│ 🟢 AI  │    │  🤖 AI       │                                │  [gpt-4o▼] │
│  │GenRp│    │  Detect      │                                │             │
│  │DetIn│    │  Intent      │                                │  Temp:     │
│  │ExtEn│    └──────┬───────┘                                │  [0.7──●──]│
│  │Sntmt│           │                                        │             │
│        │     ┌──────┴──────┐                                │  System    │
│ 🟡 Act │     │             │                                │  Prompt:   │
│  │SendM│     ▼             ▼                                │  ┌───────┐ │
│  │SndIm│  ┌────────┐  ┌────────┐                          │  │You are│ │
│  │SndQr│  │place_  │  │product│                          │  │ZamZam │ │
│  │SndPc│  │order   │  │search │                          │  │agent..│ │
│  │SndPl│  └───┬────┘  └───┬────┘                          │  └───────┘ │
│  │SrchP│      │            │                                │             │
│  │AddCa│      ▼            ▼                                │  Fallback: │
│  │PlcOr│  ┌────────┐  ┌────────┐                          │  [Human ▼] │
│  │ChkOr│  │ 💬 Send │  │ 📦 Send│                          │             │
│        │  │Confirm │  │Product│                          │  [🗑️ Del]  │
│ 🔴 Log │  │Message │  │ Card  │                          │  [📋 Copy] │
│  │IfEls│  └────────┘  └────────┘                          │             │
│  │Dely │                                                    │             │
│  │Varbl│  ┌─────────────────────────────────────────┐     │             │
│        │  │              Minimap                     │     │             │
│ 🟣 Hum │  └─────────────────────────────────────────┘     │             │
│  │Hndof│                                                    │             │
│  │Assgn│  [+ Zoom] [- Zoom] [🔄 Reset] [📋 Export JSON]    │             │
│  │Tmer │                                                    │             │
│  │Tag  │                                                    │             │
│        │                                                    │             │
│ 🟠 Int │                                                    │             │
│  │WASnd│                                                    │             │
│  │MsgSn│                                                    │             │
│  │HTTP │                                                    │             │
│  │Email│                                                    │             │
└────────┴────────────────────────────────────────────────────┴─────────────┘
```

### Node Design (Custom Vue Flow Nodes)

```
Trigger Node (blue header):
┌─────────────────────────────┐
│ 🔵 Message Received          │
├─────────────────────────────┤
│ Channel: All ▼              │
├─────────────────────────────┤
│ ○ in              ● out ──→ │
└─────────────────────────────┘

AI Node (green header):
┌─────────────────────────────┐
│ 🟢 AI Detect Intent         │
├─────────────────────────────┤
│ Model: gpt-4o               │
├─────────────────────────────┤
│ ○ in     ● yes ──→          │
│          ○ no  ──→          │
└─────────────────────────────┘

Action Node (amber header):
┌─────────────────────────────┐
│ 🟡 Search Products          │
├─────────────────────────────┤
│ Query: {{last_message}}     │
│ Limit: 5                    │
├─────────────────────────────┤
│ ○ in              ● out ──→ │
└─────────────────────────────┘

Condition Node (red header):
┌─────────────────────────────┐
│ 🔴 Intent = place_order?    │
├─────────────────────────────┤
│ ● yes ──→   ○ no ──→       │
└─────────────────────────────┘

Human Node (purple header):
┌─────────────────────────────┐
│ 🟣 Start 50s Timer          │
├─────────────────────────────┤
│ ● timeout ──→   ● human ──→ │
└─────────────────────────────┘
```

### Edge Styles

| Type | Style |
|------|-------|
| Default | Gray dotted → → → |
| Active/Running | Blue animated flow ═══▶ |
| Condition True | Green solid ─────▶ |
| Condition False | Red dashed - - - ▶ |
| Error | Red zigzag ╱╲╱╲╱▶ |

### Workflow Test Mode

```
┌─────────────────────────────────────────────────────────────────────┐
│  ▶ Testing: "Order Collection Flow"                                 │
│                                                                     │
│  Input: "আমাকে ৫০টা কাপের দাম দিন"                                │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │ Step 1: 📩 MessageReceived → ✅ Matched                         ││
│  │ Step 2: 🤖 AIDetectIntent → ✅ Intent: product_inquiry          ││
│  │ Step 3: 🔍 SearchProducts → ✅ Found: কাপ (SKU-CUP-001)        ││
│  │ Step 4: 💬 SendMessage → ✅ "৫০টা কাপের দাম ৳২,৫০০"           ││
│  │ Step 5: ⏱️ Start50sTimer → ⏸ Paused (waiting)                  ││
│  │                                                                 ││
│  │ Simulate: [👤 Human Reply] [🤖 AI Reply] [⏰ Timeout]           ││
│  └─────────────────────────────────────────────────────────────────┘│
│                                                                     │
│  Output:                                                            │
│  ┌──────────────────────────────────────┐                           │
│  │ 🤖 "৫০টা কাপের দাম ৳২,৫০০ (৳৫০/পিস)│                           │
│  │     স্টকে আছে। কার্টে যোগ করবেন?"   │                           │
│  └──────────────────────────────────────┘                           │
└─────────────────────────────────────────────────────────────────────┘
```

### WhatsApp Provider Management

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ⚙️ WhatsApp Providers                              [+ Add Provider]   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  ⭐ Meta Cloud API                                    🟢 Active │    │
│  │  📱 +880 1XXX-XXXXXX  │  Official  │  Priority: 1               │    │
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━ 99.8% uptime                       │    │
│  │  📊 234 sent │ 2 failed │ Avg 340ms                           │    │
│  │  [⚙️ Settings] [📊 Logs] [🔌 Test]                             │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  WAsender                                              🟢 Active │    │
│  │  📱 +880 1YYY-YYYYYY  │  Unofficial  │  Priority: 2           │    │
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━ 97.2% uptime                       │    │
│  │  ⚠️ Rate limit hit 3 times today                                │    │
│  │  [⚙️ Settings] [📊 Logs] [🔌 Test]                             │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  FlockSend (Custom)                                   🔴 Error  │    │
│  │  🔴 Connection failed: API key expired                          │    │
│  │  [⚙️ Settings] [🔌 Reconnect] [🗑️ Remove]                    │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                         │
│  Default Provider: [Meta Cloud API ▼]  ☑ Enable auto-fallback         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Add Provider Wizard (Card-style)

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Add WhatsApp Provider                                                  │
│                                                                         │
│  Step 1: Choose Type                                                    │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐                  │
│  │  📋 Pre-built │  │  🔧 Custom   │  │  📡 Import   │                  │
│  │  Select from │  │  HTTP Driver │  │  Paste spec  │                  │
│  │  our list    │  │  Map API     │  │  OpenAPI     │                  │
│  └──────────────┘  └──────────────┘  └──────────────┘                  │
│       ✓ Selected                                                       │
│                                                                         │
│  Step 2: Select Provider                                                │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐   │
│  │ Meta   │ │ Twilio │ │WAsender│ │UltraMsg│ │Maytapi │ │Evolut. │   │
│  │ Cloud  │ │        │ │        │ │        │ │        │ │  API   │   │
│  └────────┘ └────────┘ └────────┘ └────────┘ └────────┘ └────────┘   │
│                                                                         │
│  Step 3: Configure                                                      │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Provider Name: [WAsender - Main Number     ]                    │  │
│  │  Phone Number: [+880 1XXX-XXXXXX            ]                    │  │
│  │  🔑 API Key:  [••••••••••••••••••••••••  ] 👁️                    │  │
│  │  Instance ID: [••••••••••••••••••••••••  ] 👁️                    │  │
│  │  ☑ Set as default provider                                        │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  Step 4: Test Connection                                                │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  [🔌 Test Connection]                                             │  │
│  │  ✅ Connection successful!  Latency: 420ms                       │  │
│  │  Send test message to: [01XXXXXXXXX]  [📤 Send Test]             │  │
│  │  ✅ Test message delivered!                                       │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  [← Back]  [💾 Save & Activate]  [💾 Save as Draft]                    │
└─────────────────────────────────────────────────────────────────────────┘
```

### Custom HTTP Driver Config (No-Code)

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Custom API Mapping: FlockSend                                         │
│                                                                         │
│  ┌─ Send Text ──────────────────────────────────────────────────── 🗑️ ┐│
│  │                                                                     ││
│  │  Method: [POST ▼]   Endpoint: [/v1/messages/send              ]    ││
│  │                                                                     ││
│  │  Headers:                                                           ││
│  │  ┌─────────────────┬──────────────────────────────────────┐        ││
│  │  │ Key             │ Value                                  │  [+] ││
│  │  ├─────────────────┼──────────────────────────────────────┤        ││
│  │  │ Authorization   │ Bearer {{api_key}}                     │  [-] ││
│  │  │ Content-Type    │ application/json                       │  [-] ││
│  │  └─────────────────┴──────────────────────────────────────┘        ││
│  │                                                                     ││
│  │  Body:                                                              ││
│  │  ┌──────────────────────────────────────────────────────────┐      ││
│  │  │  { "phone": "{{to}}", "message": "{{body}}" }           │      ││
│  │  └──────────────────────────────────────────────────────────┘      ││
│  │                                                                     ││
│  │  Response Mapping:                                                  ││
│  │  Message ID path:  [data.messageId            ]                     ││
│  │                                                                     ││
│  │  [🧪 Test]  ✅ Mapping verified                                    ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                         │
│  [+ Add Action Mapping]  (send_media, send_template, mark_read...)     │
│                                                                         │
│  Available Variables: {{api_key}}, {{instance_id}}, {{to}}, {{body}},  │
│  {{media_url}}, {{caption}}, {{template_name}}, {{message_id}}          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Mobile Responsive Chat

```
┌──────────────────────┐
│ ← Rahim Store  🟢   │
│ WhatsApp • AI Active │
├──────────────────────┤
│                      │
│ 👤 মগ এর দাম কত?    │
│         10:32 AM     │
│                      │
│    🤖 100টা মগ...   │
│       10:33 AM       │
│                      │
│ 👤 কার্টে যোগ        │
│         10:34 AM     │
│                      │
│    🤖 যোগ হয়েছে!    │
│       10:34 AM       │
│                      │
├──────────────────────┤
│ ⚡ AI on [👤 Take]   │
├──────────────────────┤
│ 💬 Type...   📎 📤  │
└──────────────────────┘

↕️ Swipe up → Customer Info
↕️ Swipe down → Cart View
```
