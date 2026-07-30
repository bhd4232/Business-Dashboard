<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ProjectCostItem extends Model
{
    use BelongsToCompany;

    public const CATEGORIES = ['landed_cost' => 'Landed Cost', 'local_expense' => 'Local Expense'];

    protected $fillable = ['company_id', 'project_id', 'category', 'label', 'amount', 'purchase_id', 'remarks'];

    protected $casts = ['amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if ($project = InvestmentProject::query()->find($item->project_id)) {
                $item->company_id = $project->company_id;
            }
        });
    }

    public function project()
    {
        return $this->belongsTo(InvestmentProject::class, 'project_id');
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
}
