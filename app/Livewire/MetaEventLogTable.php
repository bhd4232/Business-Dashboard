<?php

namespace App\Livewire;

use App\Filament\Resources\StorefrontMetaEvents\StorefrontMetaEventResource;
use App\Models\StorefrontMetaEvent;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The Meta CAPI "Event Log & Retries" table, embedded inside the
 * Integrations page's Meta Pixel & CAPI tab via
 * Filament\Schemas\Components\Livewire::make() — same pattern as
 * App\Livewire\OrderTrashTable's embedding in the order trash modal. Reuses
 * StorefrontMetaEventResource::table() unchanged (columns/filters/retry
 * action aren't redefined here); StorefrontMetaEvent's own BelongsToCompany
 * scope keeps the query company-scoped automatically.
 */
class MetaEventLogTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasPermission('sales.view') ?? false, 403);
    }

    public function table(Table $table): Table
    {
        return StorefrontMetaEventResource::table($table)
            ->query(StorefrontMetaEvent::query());
    }

    public function render(): View
    {
        return view('livewire.meta-event-log-table');
    }
}
