<?php

namespace App\Filament\Resources\PayoutBatches\Pages;

use App\Filament\Resources\PayoutBatches\PayoutBatchResource;
use App\Models\Company;
use App\Services\CompanyContext;
use App\Services\Payouts\PayoutBatchService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

class ListPayoutBatches extends ListRecords
{
    protected static string $resource = PayoutBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateBatch')
                ->label('Generate New Batch')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible(fn (): bool => Auth::user()?->hasPermission('investments.settle') ?? false)
                ->schema([
                    Select::make('source')
                        ->label('Source')
                        ->options([
                            'investor' => 'Investor & Channel-Partner payouts (bank)',
                            'reseller' => 'Reseller commissions',
                        ])
                        ->native(false)
                        ->required()
                        ->live(),
                    Select::make('method')
                        ->label('Payout Method')
                        ->options([
                            'bank' => 'Bank Account (BEFTN)',
                            'mfs_bkash' => 'bKash',
                            'mfs_nagad' => 'Nagad',
                            'mfs_rocket' => 'Rocket',
                        ])
                        ->native(false)
                        ->required()
                        ->visible(fn (Get $get): bool => $get('source') === 'reseller'),
                ])
                ->requiresConfirmation()
                ->modalDescription('Pulls in every eligible pending payout that has complete payout details and is not already in another batch.')
                ->action(function (array $data): void {
                    /** @var Company $company */
                    $company = app(CompanyContext::class)->company();
                    $service = app(PayoutBatchService::class);

                    $batch = $data['source'] === 'reseller'
                        ? $service->createBatchFromPendingResellerCommissions($company, $data['method'], Auth::user())
                        : $service->createBatchFromPendingInvestorPayouts($company, Auth::user());

                    Notification::make()->success()->title('Payout batch created')
                        ->body("{$batch->batch_number} — {$batch->total_items} item(s), review and approve before generating the export file.")
                        ->send();

                    $this->redirect(PayoutBatchResource::getUrl('view', ['record' => $batch]));
                }),
        ];
    }
}
