<?php

use App\Models\StorefrontPaymentMethod;
use App\Models\StorefrontSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront checkout payment methods were hardcoded to exactly three
 * options (Cash on Delivery, bKash manual, Nagad manual), each gated by its
 * own StorefrontSetting column — adding any other channel (Rocket, Upay,
 * bank transfer, ...) meant a code change. This table makes the checkout's
 * payment method list fully admin-managed per company: any number of
 * `manual` (send-money/bank-transfer style) channels, plus one `cod` row
 * and one `online_gateway` row (the existing ZiniPay/PayStation connection,
 * offered here as a normal "pay the whole order online" choice rather than
 * only the mandatory new-customer/pre-order advance flow).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('account_number')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        // Preserve every company's current checkout options exactly as they
        // already behave today, so upgrading never silently removes a
        // payment channel a customer could use yesterday.
        StorefrontSetting::withoutGlobalScopes()->get()->each(function (StorefrontSetting $setting): void {
            $sort = 0;

            DB::table('storefront_payment_methods')->insert([
                'company_id' => $setting->company_id,
                'type' => StorefrontPaymentMethod::TYPE_COD,
                'name' => 'Cash on Delivery',
                'is_active' => $setting->cod_enabled ?? true,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (filled($setting->manual_bkash_number)) {
                DB::table('storefront_payment_methods')->insert([
                    'company_id' => $setting->company_id,
                    'type' => StorefrontPaymentMethod::TYPE_MANUAL,
                    'name' => 'bKash (Send Money)',
                    'account_number' => $setting->manual_bkash_number,
                    'instructions' => $setting->manual_bkash_instructions,
                    'is_active' => true,
                    'sort_order' => $sort++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (filled($setting->manual_nagad_number)) {
                DB::table('storefront_payment_methods')->insert([
                    'company_id' => $setting->company_id,
                    'type' => StorefrontPaymentMethod::TYPE_MANUAL,
                    'name' => 'Nagad (Send Money)',
                    'account_number' => $setting->manual_nagad_number,
                    'instructions' => $setting->manual_nagad_instructions,
                    'is_active' => true,
                    'sort_order' => $sort++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Off by default: this is a new checkout option (paying the full
            // order online) that did not exist before this migration, so an
            // admin must opt in explicitly rather than have it silently
            // appear at checkout the moment credentials happen to be set.
            DB::table('storefront_payment_methods')->insert([
                'company_id' => $setting->company_id,
                'type' => StorefrontPaymentMethod::TYPE_ONLINE_GATEWAY,
                'name' => 'Pay Online (Card/Mobile Banking)',
                'is_active' => false,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_payment_methods');
    }
};
