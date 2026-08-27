<?php

namespace App\Console\Commands;

use App\Models\StorefrontSetting;
use Illuminate\Console\Command;

class SeedDefaultFooterBlocks extends Command
{
    protected $signature = 'storefront:seed-default-footer-blocks';

    protected $description = 'One-time backfill: give every StorefrontSetting with a NULL footer_blocks column the default footer block set, so it opens pre-arranged in the footer builder instead of empty.';

    public function handle(): int
    {
        $count = StorefrontSetting::withoutGlobalScopes()
            ->whereNull('footer_blocks')
            ->update(['footer_blocks' => json_encode(StorefrontSetting::DEFAULT_FOOTER_BLOCKS)]);

        $this->info("Seeded default footer blocks for {$count} storefront setting(s).");

        return self::SUCCESS;
    }
}
