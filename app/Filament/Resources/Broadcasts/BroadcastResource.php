<?php

namespace App\Filament\Resources\Broadcasts;

use App\Filament\Clusters\Crm;
use App\Filament\Resources\Broadcasts\Pages\CreateBroadcast;
use App\Filament\Resources\Broadcasts\Pages\EditBroadcast;
use App\Filament\Resources\Broadcasts\Pages\ListBroadcasts;
use App\Filament\Resources\Broadcasts\RelationManagers\RecipientsRelationManager;
use App\Filament\Resources\Broadcasts\Schemas\BroadcastForm;
use App\Filament\Resources\Broadcasts\Tables\BroadcastsTable;
use App\Models\Broadcast;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BroadcastResource extends Resource
{
    protected static ?string $model = Broadcast::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $cluster = Crm::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BroadcastForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BroadcastsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBroadcasts::route('/'),
            'create' => CreateBroadcast::route('/create'),
            'edit' => EditBroadcast::route('/{record}/edit'),
        ];
    }

    /**
     * Broadcasting spends real WhatsApp/SMS budget and messages real
     * contacts, so it needs the stricter management permission, not just
     * CRM viewing rights.
     */
    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermission('crm.manage') ?? false;
    }
}
