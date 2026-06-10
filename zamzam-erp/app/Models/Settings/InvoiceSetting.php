<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class InvoiceSetting extends Model
{
    protected $fillable = [
        // Company / Branding
        'company_name',
        'company_tagline',
        'address',
        'hotline_1',
        'hotline_2',
        'hotline_3',
        'email',
        'website',
        'facebook',

        // Invoice Configuration
        'invoice_prefix',
        'default_payment_terms_days',
        'default_notes',
        'thank_you_message',

        // Print / PDF Display Options
        'show_product_images',
        'show_product_weight',
        'show_delivery_partner',
    ];

    protected $casts = [
        'show_product_images'       => 'boolean',
        'show_product_weight'       => 'boolean',
        'show_delivery_partner'     => 'boolean',
        'default_payment_terms_days'=> 'integer',
    ];

    /**
     * Return the single settings row, creating it with defaults if missing.
     */
    public static function instance(): self
    {
        $settings = self::find(1);

        if (! $settings) {
            $settings = new self([
                'company_name'      => 'Zamzam International',
                'address'           => 'House-59, Road-6/A, Sector-5, Uttara, Dhaka',
                'hotline_1'         => '01811754232',
                'hotline_2'         => '01894449445',
                'hotline_3'         => '01678413888',
                'email'             => 'zamzamgadgetsbd@gmail.com',
                'website'           => 'zamzamint.com',
                'facebook'          => 'facebook.com/zamzamintl',
                'invoice_prefix'    => 'INV',
                'thank_you_message' => 'Thank You For Purchasing From Us.',
                'show_product_images'   => true,
                'show_product_weight'   => true,
                'show_delivery_partner' => true,
            ]);
            $settings->save(); // auto-increments to 1 on an empty table
        }

        return $settings;
    }
}
