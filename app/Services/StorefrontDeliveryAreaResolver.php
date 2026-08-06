<?php

namespace App\Services;

use App\Models\StorefrontSetting;
use Illuminate\Support\Str;

class StorefrontDeliveryAreaResolver
{
    public const DEFAULT_INSIDE_DHAKA_KEYWORDS = [
        'dhaka', 'ঢাকা', 'dhanmondi', 'ধানমন্ডি', 'mirpur', 'মিরপুর', 'uttara', 'উত্তরা',
        'gulshan', 'গুলশান', 'banani', 'বনানী', 'mohammadpur', 'মোহাম্মদপুর', 'mohakhali', 'মহাখালী',
        'badda', 'বাড্ডা', 'bashundhara', 'বসুন্ধরা', 'motijheel', 'মতিঝিল', 'farmgate', 'ফার্মগেট',
        'tejgaon', 'তেজগাঁও', 'ramna', 'রমনা', 'jatrabari', 'যাত্রাবাড়ী', 'যাত্রাবাড়ী',
        'khilgaon', 'খিলগাঁও', 'khilkhet', 'খিলক্ষেত', 'pallabi', 'পল্লবী', 'shyamoli', 'শ্যামলী',
        'wari', 'ওয়ারী', 'ওয়ারী', 'lalbagh', 'লালবাগ', 'old dhaka', 'পুরান ঢাকা',
        'airport', 'এয়ারপোর্ট', 'এয়ারপোর্ট', 'cantonment', 'ক্যান্টনমেন্ট', 'agargaon', 'আগারগাঁও',
        'demra', 'ডেমরা', 'kafrul', 'কাফরুল', 'kamrangirchar', 'কামরাঙ্গীরচর',
    ];

    /** Places commonly followed by "Dhaka Division" but billed outside Dhaka city. */
    public const DEFAULT_OUTSIDE_DHAKA_MARKERS = [
        'gazipur', 'গাজীপুর', 'narayanganj', 'নারায়ণগঞ্জ', 'নারায়ণগঞ্জ', 'savar', 'সাভার',
        'keraniganj', 'কেরানীগঞ্জ', 'dohar', 'দোহার', 'dhamrai', 'ধামরাই',
        'narsingdi', 'নরসিংদী', 'munshiganj', 'মুন্সিগঞ্জ', 'manikganj', 'মানিকগঞ্জ',
        'tangail', 'টাঙ্গাইল', 'faridpur', 'ফরিদপুর', 'gopalganj', 'গোপালগঞ্জ',
        'rajbari', 'রাজবাড়ী', 'রাজবাড়ী', 'madaripur', 'মাদারীপুর', 'shariatpur', 'শরীয়তপুর', 'শরীয়তপুর',
    ];

    public function resolve(string $address, StorefrontSetting $setting): string
    {
        $normalized = Str::of($address)->lower()->squish()->toString();

        if ($normalized === '') {
            return 'outside';
        }

        foreach ($this->outsideMarkers() as $marker) {
            if (Str::contains($normalized, Str::lower($marker))) {
                return 'outside';
            }
        }

        foreach ($this->keywords($setting) as $keyword) {
            if (Str::contains($normalized, Str::lower($keyword))) {
                return 'inside';
            }
        }

        return 'outside';
    }

    /** @return list<string> */
    public function keywords(StorefrontSetting $setting): array
    {
        $configured = preg_split('/[\r\n,]+/u', (string) $setting->inside_dhaka_keywords) ?: [];
        $keywords = collect($configured)
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter()
            ->values()
            ->all();

        return $keywords !== [] ? $keywords : self::DEFAULT_INSIDE_DHAKA_KEYWORDS;
    }

    /** @return list<string> */
    public function outsideMarkers(): array
    {
        return self::DEFAULT_OUTSIDE_DHAKA_MARKERS;
    }
}
