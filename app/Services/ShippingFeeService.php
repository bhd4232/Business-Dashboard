<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CourierProvider;
use App\Support\BangladeshDistricts;

class ShippingFeeService
{
    public const ZONES = ['inside', 'outside', 'suburb'];

    /**
     * Matches a free-text address against the company's admin-configured
     * area keyword lists (ERP Settings > Shipping Zones). The keyword lists
     * are always the canonical English district/area names, but a customer
     * writes their address in whatever spelling or script they use — so
     * each keyword is also matched against its Bengali name and any older
     * English spelling (BangladeshDistricts::aliasesFor()), not just its own
     * text. The first zone whose keyword list contains a match wins; returns
     * null if nothing matches so callers can fall back to no shipping fee
     * rather than guessing a zone.
     */
    public function determineZone(?string $address, Company $company): ?string
    {
        $address = mb_strtolower(trim((string) $address));

        if ($address === '') {
            return null;
        }

        $areas = (array) ($company->settings['shipping_zones'] ?? []);

        foreach (self::ZONES as $zone) {
            foreach ((array) ($areas[$zone] ?? []) as $keyword) {
                $keyword = trim((string) $keyword);

                if ($keyword === '') {
                    continue;
                }

                $candidates = [$keyword, ...BangladeshDistricts::aliasesFor($keyword)];

                foreach ($candidates as $candidate) {
                    if (str_contains($address, mb_strtolower(trim($candidate)))) {
                        return $zone;
                    }
                }
            }
        }

        return null;
    }

    /**
     * The courier whose "Set Delivery Fees" apply before an order has an
     * actual booking: the company's first active courier provider.
     */
    public function defaultCourierProvider(Company $company): ?CourierProvider
    {
        return CourierProvider::query()
            ->where('company_id', $company->getKey())
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{zone: ?string, fee: float, courier_provider_id: ?int}
     */
    public function feeFor(?string $address, Company $company): array
    {
        $zone = $this->determineZone($address, $company);
        $provider = $this->defaultCourierProvider($company);

        if (! $zone || ! $provider) {
            return [
                'zone' => $zone,
                'fee' => 0.0,
                'courier_provider_id' => $provider?->getKey(),
            ];
        }

        $fee = (float) ($provider->settings['delivery_fees'][$zone] ?? 0);

        return [
            'zone' => $zone,
            'fee' => $fee,
            'courier_provider_id' => $provider->getKey(),
        ];
    }
}
