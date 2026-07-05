<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\WooCommerceImportService;
use Illuminate\Console\Command;

class ImportWooCommerceProducts extends Command
{
    protected $signature = 'woocommerce:import-products
        {company : Company slug to import into}
        {--no-images : Skip downloading product images}';

    protected $description = 'Import published WooCommerce products into a company using the WooCommerce REST API credentials from its storefront settings';

    public function handle(WooCommerceImportService $service): int
    {
        $company = Company::query()->where('slug', $this->argument('company'))->first();

        if (! $company) {
            $this->error("Company '{$this->argument('company')}' not found.");

            return self::FAILURE;
        }

        $this->info("Importing WooCommerce products into {$company->name}...");

        try {
            $result = $service->importProducts(
                $company,
                downloadImages: ! $this->option('no-images'),
                progress: fn (string $outcome, string $name) => $this->line("  [{$outcome}] {$name}"),
            );
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Done. Created: {$result['created']}, updated: {$result['updated']}, skipped: {$result['skipped']}.");

        return self::SUCCESS;
    }
}
