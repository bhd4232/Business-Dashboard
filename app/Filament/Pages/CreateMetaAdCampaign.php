<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Ads;
use App\Models\MetaAdAccount;
use App\Models\MetaAdProposal;
use App\Models\Product;
use App\Services\CompanyContext;
use App\Services\MetaAdsCreationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Phase C: creates one real Campaign + Ad Set + Ad on Meta, tied to one
 * Product. Reached via MetaAdsDashboard's "New Campaign" header action —
 * not in the sidebar, same "linked but hidden" pattern as BulkUpdateStock.
 *
 * Deliberately narrow scope (see the Meta Ads plan): one objective
 * (OUTCOME_TRAFFIC), Bangladesh-only geo targeting, age range + gender
 * only, and the ad image always comes from the selected Product's own
 * image (no separate manual upload). Every object MetaAdsCreationService
 * creates lands PAUSED — this page never activates anything; that's a
 * separate, explicit Resume from the dashboard (Phase B).
 *
 * Phase D: also reachable via a MetaAdProposal's "Review & Launch" action
 * (?proposalId=...), which prefills the form from the AI's draft — the
 * owner still reviews/edits and explicitly submits here, the AI itself
 * never calls this page's submit() or any Meta endpoint.
 */
class CreateMetaAdCampaign extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static ?string $cluster = Ads::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'New Meta Ad Campaign';

    protected string $view = 'filament.pages.create-meta-ad-campaign';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    #[Url]
    public ?int $proposalId = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermission('marketing.create') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'account_id' => $this->accounts()->first()?->getKey(),
            'age_min' => 18,
            'age_max' => 65,
            'gender' => 'all',
            'call_to_action' => 'SHOP_NOW',
        ]);

        $proposal = $this->proposal();

        if (! $proposal) {
            return;
        }

        $this->form->fill([
            'account_id' => $proposal->meta_ad_account_id,
            'campaign_name' => $proposal->product?->name ? "{$proposal->product->name} Campaign" : null,
            'daily_budget' => (float) $proposal->suggested_budget,
            'age_min' => $proposal->targeting['age_min'] ?? 18,
            'age_max' => $proposal->targeting['age_max'] ?? 65,
            'gender' => $proposal->targeting['gender'] ?? 'all',
            'product_id' => $proposal->product_id,
            'headline' => $proposal->headline,
            'primary_text' => $proposal->primary_text,
            'description_text' => $proposal->description_text,
            'call_to_action' => $proposal->call_to_action ?: 'SHOP_NOW',
        ]);
    }

    /** The company-scoped draft proposal this page was opened from, if any. */
    public function proposal(): ?MetaAdProposal
    {
        if (! $this->proposalId) {
            return null;
        }

        return MetaAdProposal::query()
            ->where('status', MetaAdProposal::STATUS_DRAFT)
            ->find($this->proposalId);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Section::make('Campaign')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('account_id')
                            ->label('Ad Account')
                            ->options(fn (): array => $this->accounts()->pluck('name', 'id')->all())
                            ->required()
                            ->native(false)
                            ->dehydratedWhenHidden()
                            ->visible(fn (): bool => $this->accounts()->count() > 1),
                        TextInput::make('campaign_name')
                            ->label('Campaign Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('daily_budget')
                            ->label('Daily Budget')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->helperText('In the ad account\'s own currency.'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Audience')
                    ->columnSpanFull()
                    ->description('Bangladesh only for now — interest/behavior targeting isn\'t supported yet.')
                    ->schema([
                        TextInput::make('age_min')
                            ->label('Min Age')
                            ->numeric()
                            ->integer()
                            ->minValue(13)
                            ->maxValue(65)
                            ->required(),
                        TextInput::make('age_max')
                            ->label('Max Age')
                            ->numeric()
                            ->integer()
                            ->minValue(13)
                            ->maxValue(65)
                            ->gte('age_min')
                            ->required(),
                        Select::make('gender')
                            ->options(['all' => 'All', 'male' => 'Male', 'female' => 'Female'])
                            ->required()
                            ->native(false),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Ad')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->options(fn (): array => $this->eligibleProducts()->pluck('name', 'id')->all())
                            ->searchable()
                            ->required()
                            ->live()
                            ->helperText('Only products with an image can be advertised.')
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                $product = $state ? $this->eligibleProducts()->firstWhere('id', (int) $state) : null;

                                if (! $product) {
                                    return;
                                }

                                $set('headline', str($product->name)->limit(40, '')->toString());
                                $price = number_format((float) ($product->sale_price ?? $product->price), 0);
                                $set('primary_text', "Get {$product->name} now for just ৳{$price}!");
                            }),
                        Select::make('call_to_action')
                            ->label('Call To Action')
                            ->options([
                                'SHOP_NOW' => 'Shop Now',
                                'BUY_NOW' => 'Buy Now',
                                'ORDER_NOW' => 'Order Now',
                                'LEARN_MORE' => 'Learn More',
                            ])
                            ->required()
                            ->native(false),
                        TextInput::make('headline')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('primary_text')
                            ->label('Primary Text')
                            ->required()
                            ->maxLength(2000)
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('description_text')
                            ->label('Description')
                            ->maxLength(200)
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Campaign (Paused)')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->action(fn () => $this->submit()),
        ];
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $account = $this->accounts()->firstWhere('id', $data['account_id'] ?? null);

        if (! $account) {
            Notification::make()->title('Select an ad account first')->danger()->send();

            return;
        }

        try {
            $campaign = app(MetaAdsCreationService::class)->create($account, $data);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Campaign creation failed')
                ->body($this->exceptionMessage($exception))
                ->danger()
                ->send();

            return;
        }

        $this->proposal()?->forceFill([
            'status' => MetaAdProposal::STATUS_LAUNCHED,
            'meta_ad_campaign_id' => $campaign->getKey(),
        ])->save();

        Notification::make()
            ->title('Campaign created — paused')
            ->body('Review it on the dashboard, then click Resume when you\'re ready for it to start spending.')
            ->success()
            ->send();

        $this->redirect(MetaAdsDashboard::getUrl().'?accountId='.$account->getKey().'&campaignId='.$campaign->getKey());
    }

    /** @return Collection<int, MetaAdAccount> */
    public function accounts(): Collection
    {
        $companyId = app(CompanyContext::class)->id();

        return MetaAdAccount::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Product> */
    public function eligibleProducts(): Collection
    {
        $companyId = app(CompanyContext::class)->id();

        return Product::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'image', 'price', 'sale_price']);
    }

    protected function exceptionMessage(Throwable $exception): string
    {
        return $exception instanceof ValidationException
            ? collect($exception->errors())->flatten()->implode(' ')
            : $exception->getMessage();
    }
}
