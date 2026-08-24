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
}
