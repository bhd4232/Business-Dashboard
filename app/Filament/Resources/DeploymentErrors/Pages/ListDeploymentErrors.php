<?php

namespace App\Filament\Resources\DeploymentErrors\Pages;

use App\Filament\Resources\DeploymentErrors\DeploymentErrorResource;
use Filament\Resources\Pages\ListRecords;

class ListDeploymentErrors extends ListRecords
{
    protected static string $resource = DeploymentErrorResource::class;
}
