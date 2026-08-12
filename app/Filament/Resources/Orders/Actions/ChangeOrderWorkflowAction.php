<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Services\OrderStatusWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

class ChangeOrderWorkflowAction
{
    public static function make(): Action
    {
        return Action::make('changeWorkflowStatus')
            ->label('Change status')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->visible(fn (Order $record): bool => filled($record->allowedWorkflowStages())
                && (Auth::user()?->hasPermission('sales.update') ?? false))
            ->modalHeading(fn (Order $record): string => "Change status for {$record->order_number}")
            ->modalDescription('Shipping and delivery stages also update the storefront delivery status. Stock and customer dues follow the order status.')
            ->schema(fn (Order $record): array => [
                Select::make('stage')
                    ->label('Next stage')
                    ->options($record->allowedWorkflowStageOptions())
                    ->required()
                    ->live()
                    ->native(false),
                Textarea::make('reason')
                    ->label('Reason / note')
                    ->rows(3)
                    ->maxLength(1000)
                    ->required(fn (Get $get): bool => in_array($get('stage'), Order::REASON_REQUIRED_STAGES, true))
                    ->helperText('Required for cancellation, return, and refund so the history remains actionable.'),
            ])
            ->requiresConfirmation()
            ->action(function (Order $record, array $data): void {
                app(OrderStatusWorkflowService::class)->transition(
                    $record,
                    $data['stage'],
                    $data['reason'] ?? null,
                );

                $record->refresh();
            })
            ->successNotificationTitle('Order status updated');
    }
}
