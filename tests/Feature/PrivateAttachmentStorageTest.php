<?php

namespace Tests\Feature;

use App\Filament\Resources\Vouchers\Pages\CreateVoucher;
use App\Jobs\DownloadConversationMediaJob;
use App\Models\Account;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationChannel;
use App\Models\ConversationMessage;
use App\Models\ExpenseCategory;
use App\Models\LegacyPrivateStoragePath;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use App\Services\Meta\MetaGraphException;
use App\Services\Meta\MetaGraphService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PrivateAttachmentStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_conversation_media_job_writes_to_the_company_private_prefix(): void
    {
        $company = $this->createCompany('Media Company', 'MED');
        $context = app(CompanyContext::class)->set($company);
        $channel = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => 'phone-number-1',
            'display_name' => 'Main WhatsApp',
            'access_token' => 'secret-token',
        ]);
        $conversation = Conversation::query()->create([
            'channel_id' => $channel->getKey(),
            'provider' => 'whatsapp',
            'external_contact_id' => '8801700000000',
        ]);
        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'incoming',
            'type' => 'image',
            'external_message_id' => 'wamid/media:1',
            'sent_at' => now(),
        ]);

        Http::fake([
            'https://lookaside.facebook.com/photo' => Http::response('private-image-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        (new DownloadConversationMediaJob(
            messageId: $message->getKey(),
            channelId: $channel->getKey(),
            mediaUrl: 'https://lookaside.facebook.com/photo',
        ))->handle($context, app(CompanyStorageService::class));

        $message->refresh();

        $this->assertStringStartsWith($company->storageRoot().'/private/conversation-media/', $message->media_path);
        Storage::disk('local')->assertExists($message->media_path);
        $this->assertSame('private-image-bytes', Storage::disk('local')->get($message->media_path));
        $this->assertFalse($context->hasCompany());
    }

    public function test_successful_media_retry_clears_only_its_recorded_channel_error(): void
    {
        $company = $this->createCompany('Media Retry Company', 'MRC');
        $context = app(CompanyContext::class)->set($company);
        $channel = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => 'media-retry-phone',
            'display_name' => 'Media Retry WhatsApp',
            'access_token' => 'media-retry-token',
            'last_inbound_at' => now(),
        ]);
        $conversation = Conversation::query()->create([
            'channel_id' => $channel->getKey(),
            'provider' => 'whatsapp',
            'external_contact_id' => '8801700000001',
        ]);
        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'incoming',
            'type' => 'image',
            'external_message_id' => 'wamid-media-retry',
            'sent_at' => now(),
        ]);
        Http::fake([
            'https://lookaside.facebook.com/retry-photo' => Http::sequence()
                ->push(['error' => ['message' => 'Temporary media error', 'code' => 100]], 400)
                ->push('retried-private-image', 200, ['Content-Type' => 'image/png']),
        ]);
        $job = new DownloadConversationMediaJob(
            messageId: $message->getKey(),
            channelId: $channel->getKey(),
            mediaUrl: 'https://lookaside.facebook.com/retry-photo',
        );

        try {
            $job->handle($context, app(CompanyStorageService::class));
            $this->fail('The first media attempt should fail.');
        } catch (MetaGraphException) {
            $this->assertSame('media', $channel->fresh()->last_error_source);
            $this->assertSame('Needs attention', $channel->fresh()->diagnosticStatus());
        }

        $job->handle($context, app(CompanyStorageService::class));

        $this->assertNotNull($message->fresh()->media_path);
        $this->assertNull($channel->fresh()->last_error);
        $this->assertSame('Inbound confirmed', $channel->fresh()->diagnosticStatus());
    }

    public function test_conversation_media_job_rejects_a_message_from_another_channel_and_company(): void
    {
        $companyA = $this->createCompany('Channel Company A', 'CHA');
        $companyB = $this->createCompany('Channel Company B', 'CHB');
        $context = app(CompanyContext::class)->set($companyA);
        $channelA = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => 'channel-a',
            'display_name' => 'Channel A',
        ]);

        $context->set($companyB);
        $channelB = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => 'channel-b',
            'display_name' => 'Channel B',
        ]);
        $conversationB = Conversation::query()->create([
            'channel_id' => $channelB->getKey(),
            'provider' => 'whatsapp',
            'external_contact_id' => 'contact-b',
        ]);
        $messageB = ConversationMessage::query()->create([
            'conversation_id' => $conversationB->getKey(),
            'direction' => 'incoming',
            'type' => 'image',
            'external_message_id' => 'message-b',
            'sent_at' => now(),
        ]);

        try {
            (new DownloadConversationMediaJob(
                messageId: $messageB->getKey(),
                channelId: $channelA->getKey(),
                mediaUrl: 'https://media.example.test/wrong',
            ))->handle($context, app(CompanyStorageService::class));

            $this->fail('A message from another company should not resolve for this channel.');
        } catch (ModelNotFoundException) {
            $this->assertFalse($context->hasCompany());
            $this->assertNull($messageB->fresh()->media_path);
        }
    }

    public function test_private_media_and_voucher_downloads_are_denied_across_companies(): void
    {
        $companyA = $this->createCompany('Private Company A', 'PRA');
        $companyB = $this->createCompany('Private Company B', 'PRB');
        $userA = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $userB = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $userA->companies()->sync([$companyA->getKey() => ['role' => 'manager', 'is_default' => true]]);
        $userB->companies()->sync([$companyB->getKey() => ['role' => 'manager', 'is_default' => true]]);

        [$messageA, $attachmentA] = $this->createPrivateRecords($companyA, $userA, 'company-a');
        [$messageB, $attachmentB] = $this->createPrivateRecords($companyB, $userB, 'company-b');

        app(CompanyContext::class)->set($companyA);
        Storage::disk('local')->put('conversations/legacy-a.png', 'legacy-company-a-media');
        LegacyPrivateStoragePath::query()->create([
            'path' => 'conversations/legacy-a.png',
            'company_id' => $companyA->getKey(),
        ]);
        $legacyConversation = Conversation::query()->create(['provider' => 'manual', 'contact_name' => 'Legacy contact']);
        $legacyMessage = ConversationMessage::query()->create([
            'conversation_id' => $legacyConversation->getKey(),
            'direction' => 'incoming',
            'type' => 'image',
            'media_path' => 'conversations/legacy-a.png',
            'media_mime' => 'image/png',
            'sent_at' => now(),
        ]);
        Storage::disk('local')->put('voucher-attachments/legacy-a.pdf', 'legacy-company-a-voucher');
        LegacyPrivateStoragePath::query()->create([
            'path' => 'voucher-attachments/legacy-a.pdf',
            'company_id' => $companyA->getKey(),
        ]);
        $legacyAttachment = VoucherAttachment::query()->create([
            'voucher_id' => $attachmentA->voucher_id,
            'file_path' => 'voucher-attachments/legacy-a.pdf',
            'file_type' => 'application/pdf',
        ]);

        $this->actingAs($userA)->withSession(['current_company_id' => $companyA->getKey()]);

        $mediaResponse = $this->get(route('conversation-messages.media', ['message' => $messageA->getKey()]));
        $mediaResponse
            ->assertOk()
            ->assertStreamedContent('company-a-media');
        $this->assertStringContainsString('private', (string) $mediaResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $mediaResponse->headers->get('Cache-Control'));
        $this->get(route('conversation-messages.media', ['message' => $messageB->getKey()]))
            ->assertNotFound();
        $this->get(route('conversation-messages.media', ['message' => $legacyMessage->getKey()]))
            ->assertOk()
            ->assertStreamedContent('legacy-company-a-media');

        $inventoryUser = User::factory()->create(['role' => 'inventory_staff', 'is_active' => true]);
        $inventoryUser->companies()->sync([$companyA->getKey() => ['role' => 'inventory_staff', 'is_default' => true]]);
        $this->actingAs($inventoryUser)->withSession(['current_company_id' => $companyA->getKey()]);
        $this->get(route('conversation-messages.media', ['message' => $messageA->getKey()]))
            ->assertNotFound();

        $this->actingAs($userA)->withSession(['current_company_id' => $companyA->getKey()]);

        $this->get(route('voucher-attachments.download', ['attachment' => $attachmentA->getKey()]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=company-a.pdf')
            ->assertStreamedContent('company-a-voucher');
        $this->get(route('voucher-attachments.download', ['attachment' => $attachmentB->getKey()]))
            ->assertNotFound();
        $this->get(route('voucher-attachments.download', ['attachment' => $legacyAttachment->getKey()]))
            ->assertOk()
            ->assertStreamedContent('legacy-company-a-voucher');

        DB::table('users')->where('id', $userA->getKey())->update(['is_active' => false]);
        $userA->refresh();
        $this->actingAs($userA)->withSession(['current_company_id' => $companyA->getKey()]);
        $this->get(route('conversation-messages.media', ['message' => $messageA->getKey()]))
            ->assertNotFound();
        $this->get(route('voucher-attachments.download', ['attachment' => $attachmentA->getKey()]))
            ->assertNotFound();

        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($superAdmin)->withSession(['current_company_id' => 'all']);
        $this->get(route('conversation-messages.media', ['message' => $messageB->getKey()]))
            ->assertOk()
            ->assertStreamedContent('company-b-media');
        $this->get(route('voucher-attachments.download', ['attachment' => $attachmentB->getKey()]))
            ->assertOk()
            ->assertStreamedContent('company-b-voucher');
    }

    public function test_voucher_form_stores_new_attachments_in_private_company_storage(): void
    {
        $company = $this->createCompany('Voucher Upload Company', 'VUC');
        app(CompanyContext::class)->set($company);
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($user);
        $account = Account::query()->create(['name' => 'Voucher Cash', 'type' => 'cash', 'opening_balance' => 1000]);
        $category = ExpenseCategory::query()->create(['name' => 'Voucher Expense', 'slug' => 'voucher-expense']);

        Livewire::test(CreateVoucher::class)
            ->fillForm([
                'type' => Voucher::TYPE_DEBIT,
                'transaction_type' => 'business_expense',
                'amount' => 125,
                'account_id' => $account->getKey(),
                'expense_category_id' => $category->getKey(),
                'attachments' => [[
                    'file_path' => [UploadedFile::fake()->image('receipt.png', 120, 120)],
                    'label' => 'Payment receipt',
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $attachment = VoucherAttachment::query()->firstOrFail();

        $this->assertSame($company->getKey(), $attachment->company_id);
        $this->assertStringStartsWith($company->storageRoot().'/private/voucher-attachments/', $attachment->file_path);
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_declared_oversized_meta_media_is_rejected_before_downloading(): void
    {
        config(['services.meta.max_media_bytes' => 10]);
        $company = $this->createCompany('Media Limit Company', 'MLC');
        app(CompanyContext::class)->set($company);
        $channel = ConversationChannel::query()->create([
            'provider' => 'whatsapp',
            'external_id' => 'media-limit-phone',
            'display_name' => 'Media Limit WhatsApp',
            'access_token' => 'media-limit-token',
            'is_active' => true,
        ]);
        Http::preventStrayRequests();

        try {
            app(MetaGraphService::class)->downloadMedia(
                $channel,
                'https://lookaside.facebook.com/whatsapp_business/attachments/example',
                11,
            );
            $this->fail('An oversized attachment should be rejected before the HTTP request.');
        } catch (MetaGraphException $exception) {
            $this->assertStringContainsString('larger than the configured media limit', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    /**
     * @return array{0: ConversationMessage, 1: VoucherAttachment}
     */
    protected function createPrivateRecords(Company $company, User $user, string $prefix): array
    {
        app(CompanyContext::class)->set($company);
        $storage = app(CompanyStorageService::class);
        $conversation = Conversation::query()->create([
            'provider' => 'manual',
            'contact_name' => "{$prefix} contact",
        ]);
        $mediaPath = $storage->putPrivate($company, 'conversation-media', "{$prefix}.png", "{$prefix}-media");
        $message = ConversationMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'direction' => 'incoming',
            'type' => 'image',
            'media_path' => $mediaPath,
            'media_mime' => 'image/png',
            'sent_at' => now(),
        ]);
        $voucher = Voucher::query()->create([
            'voucher_number' => 'DV-'.str($prefix)->upper(),
            'type' => Voucher::TYPE_DEBIT,
            'status' => Voucher::STATUS_PENDING,
            'transaction_type' => 'other',
            'amount' => 10,
            'submitted_by' => $user->getKey(),
        ]);
        $voucherPath = $storage->putPrivate($company, 'voucher-attachments', "{$prefix}.pdf", "{$prefix}-voucher");
        $attachment = VoucherAttachment::query()->create([
            'voucher_id' => $voucher->getKey(),
            'file_path' => $voucherPath,
            'file_type' => 'application/pdf',
            'label' => "{$prefix} receipt",
        ]);

        return [$message, $attachment];
    }

    protected function createCompany(string $name, string $prefix): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'invoice_prefix' => $prefix,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }
}
