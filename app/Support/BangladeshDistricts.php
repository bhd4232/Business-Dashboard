<?php

namespace App\Support;

class BangladeshDistricts
{
    /**
     * The 64 districts of Bangladesh, grouped by division in the order
     * divisions are commonly listed. Used to power area/district dropdowns
     * (e.g. Company Settings > Shipping Zones) instead of free-text entry.
     *
     * @var array<string, list<string>>
     */
    public const BY_DIVISION = [
        'Dhaka' => [
            'Dhaka', 'Faridpur', 'Gazipur', 'Gopalganj', 'Kishoreganj', 'Madaripur',
            'Manikganj', 'Munshiganj', 'Narayanganj', 'Narsingdi', 'Rajbari',
            'Shariatpur', 'Tangail',
        ],
        'Chattogram' => [
            'Bandarban', 'Brahmanbaria', 'Chandpur', 'Chattogram', 'Cumilla',
            'Cox\'s Bazar', 'Feni', 'Khagrachhari', 'Lakshmipur', 'Noakhali',
            'Rangamati',
        ],
        'Rajshahi' => [
            'Bogura', 'Chapainawabganj', 'Joypurhat', 'Naogaon', 'Natore',
            'Pabna', 'Rajshahi', 'Sirajganj',
        ],
        'Khulna' => [
            'Bagerhat', 'Chuadanga', 'Jashore', 'Jhenaidah', 'Khulna', 'Kushtia',
            'Magura', 'Meherpur', 'Narail', 'Satkhira',
        ],
        'Barishal' => [
            'Barguna', 'Barishal', 'Bhola', 'Jhalokati', 'Patuakhali', 'Pirojpur',
        ],
        'Sylhet' => [
            'Habiganj', 'Moulvibazar', 'Sunamganj', 'Sylhet',
        ],
        'Rangpur' => [
            'Dinajpur', 'Gaibandha', 'Kurigram', 'Lalmonirhat', 'Nilphamari',
            'Panchagarh', 'Rangpur', 'Thakurgaon',
        ],
        'Mymensingh' => [
            'Jamalpur', 'Mymensingh', 'Netrokona', 'Sherpur',
        ],
    ];

    /**
     * Peri-urban / "sub-urban" areas immediately around Dhaka city — the
     * zone couriers typically price between "inside Dhaka" and "outside
     * Dhaka" (a separate concept from a full district).
     *
     * @var list<string>
     */
    public const SUBURB_AREAS = [
        'Savar', 'Ashulia', 'Dhamrai', 'Keraniganj', 'Demra', 'Tongi',
        'Gazipur City', 'Narayanganj City', 'Kaliakair', 'Sreepur',
        'Rupganj', 'Munshiganj Sadar', 'Narsingdi Sadar', 'Manikganj Sadar',
    ];

    /** @return array<string, string> Options keyed and valued by district name, for Filament Select. */
    public static function options(): array
    {
        $names = collect(self::BY_DIVISION)->flatten()->sort()->values()->all();

        return array_combine($names, $names);
    }

    /** @return array<string, string> Options keyed and valued by suburb area name, for Filament Select. */
    public static function suburbOptions(): array
    {
        return array_combine(self::SUBURB_AREAS, self::SUBURB_AREAS);
    }

    /**
     * Bengali names and common older/informal English spellings for each
     * district or suburb area, keyed by the canonical name stored in a
     * company's Shipping Zones settings (the values from options() /
     * suburbOptions()). Customers write their address in whichever spelling
     * or script they're used to, so ShippingFeeService::determineZone()
     * matches an address against these too, not just the canonical name.
     *
     * @var array<string, list<string>>
     */
    public const ALIASES = [
        'Dhaka' => ['ঢাকা'],
        'Faridpur' => ['ফরিদপুর'],
        'Gazipur' => ['গাজীপুর'],
        'Gopalganj' => ['গোপালগঞ্জ'],
        'Kishoreganj' => ['কিশোরগঞ্জ'],
        'Madaripur' => ['মাদারীপুর'],
        'Manikganj' => ['মানিকগঞ্জ'],
        'Munshiganj' => ['মুন্সিগঞ্জ'],
        'Narayanganj' => ['নারায়ণগঞ্জ'],
        'Narsingdi' => ['নরসিংদী'],
        'Rajbari' => ['রাজবাড়ী'],
        'Shariatpur' => ['শরীয়তপুর'],
        'Tangail' => ['টাঙ্গাইল'],
        'Bandarban' => ['বান্দরবান'],
        'Brahmanbaria' => ['ব্রাহ্মণবাড়িয়া'],
        'Chandpur' => ['চাঁদপুর'],
        'Chattogram' => ['চট্টগ্রাম', 'Chittagong'],
        'Cumilla' => ['কুমিল্লা', 'Comilla'],
        'Cox\'s Bazar' => ['কক্সবাজার', 'Coxs Bazar', 'Cox Bazar'],
        'Feni' => ['ফেনী'],
        'Khagrachhari' => ['খাগড়াছড়ি', 'Khagrachari'],
        'Lakshmipur' => ['লক্ষ্মীপুর'],
        'Noakhali' => ['নোয়াখালী'],
        'Rangamati' => ['রাঙ্গামাটি'],
        'Bogura' => ['বগুড়া', 'Bogra'],
        'Chapainawabganj' => ['চাঁপাইনবাবগঞ্জ', 'Chapai Nawabganj', 'Nawabganj'],
        'Joypurhat' => ['জয়পুরহাট', 'Jaipurhat'],
        'Naogaon' => ['নওগাঁ'],
        'Natore' => ['নাটোর'],
        'Pabna' => ['পাবনা'],
        'Rajshahi' => ['রাজশাহী'],
        'Sirajganj' => ['সিরাজগঞ্জ'],
        'Bagerhat' => ['বাগেরহাট'],
        'Chuadanga' => ['চুয়াডাঙ্গা'],
        'Jashore' => ['যশোর', 'Jessore'],
        'Jhenaidah' => ['ঝিনাইদহ'],
        'Khulna' => ['খুলনা'],
        'Kushtia' => ['কুষ্টিয়া'],
        'Magura' => ['মাগুরা'],
        'Meherpur' => ['মেহেরপুর'],
        'Narail' => ['নড়াইল'],
        'Satkhira' => ['সাতক্ষীরা'],
        'Barguna' => ['বরগুনা'],
        'Barishal' => ['বরিশাল', 'Barisal'],
        'Bhola' => ['ভোলা'],
        'Jhalokati' => ['ঝালকাঠি'],
        'Patuakhali' => ['পটুয়াখালী'],
        'Pirojpur' => ['পিরোজপুর'],
        'Habiganj' => ['হবিগঞ্জ'],
        'Moulvibazar' => ['মৌলভীবাজার', 'Maulvibazar'],
        'Sunamganj' => ['সুনামগঞ্জ'],
        'Sylhet' => ['সিলেট'],
        'Dinajpur' => ['দিনাজপুর'],
        'Gaibandha' => ['গাইবান্ধা'],
        'Kurigram' => ['কুড়িগ্রাম'],
        'Lalmonirhat' => ['লালমনিরহাট'],
        'Nilphamari' => ['নীলফামারী'],
        'Panchagarh' => ['পঞ্চগড়'],
        'Rangpur' => ['রংপুর'],
        'Thakurgaon' => ['ঠাকুরগাঁও'],
        'Jamalpur' => ['জামালপুর'],
        'Mymensingh' => ['ময়মনসিংহ'],
        'Netrokona' => ['নেত্রকোনা', 'Netrakona'],
        'Sherpur' => ['শেরপুর'],
        'Savar' => ['সাভার'],
        'Ashulia' => ['আশুলিয়া'],
        'Dhamrai' => ['ধামরাই'],
        'Keraniganj' => ['কেরানীগঞ্জ'],
        'Demra' => ['ডেমরা'],
        'Tongi' => ['টঙ্গী'],
        'Gazipur City' => ['গাজীপুর সিটি'],
        'Narayanganj City' => ['নারায়ণগঞ্জ সিটি'],
        'Kaliakair' => ['কালিয়াকৈর'],
        'Sreepur' => ['শ্রীপুর'],
        'Rupganj' => ['রূপগঞ্জ'],
        'Munshiganj Sadar' => ['মুন্সিগঞ্জ সদর'],
        'Narsingdi Sadar' => ['নরসিংদী সদর'],
        'Manikganj Sadar' => ['মানিকগঞ্জ সদর'],
    ];

    /**
     * @return list<string> Extra spellings (Bangla name and/or an older
     *     English spelling) that should also match this canonical district
     *     or area name — not including the canonical name itself.
     */
    public static function aliasesFor(string $name): array
    {
        return self::ALIASES[$name] ?? [];
    }
}
