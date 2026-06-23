<?php

namespace App\Services;

use App\Models\CourierBooking;
use Illuminate\Support\Collection;

class CourierReportService
{
    public function providerPerformance(): Collection
    {
        return CourierBooking::query()
            ->join('courier_providers', 'courier_providers.id', '=', 'courier_bookings.courier_provider_id')
            ->select('courier_providers.id', 'courier_providers.name', 'courier_providers.driver')
            ->selectRaw('COUNT(courier_bookings.id) as total')
            ->selectRaw('SUM(CASE WHEN courier_bookings.status = ? THEN 1 ELSE 0 END) as delivered', [CourierBooking::STATUS_DELIVERED])
            ->selectRaw('SUM(CASE WHEN courier_bookings.status = ? THEN 1 ELSE 0 END) as returned', [CourierBooking::STATUS_RETURNED])
            ->selectRaw('SUM(CASE WHEN courier_bookings.status = ? THEN 1 ELSE 0 END) as cancelled', [CourierBooking::STATUS_CANCELLED])
            ->selectRaw('SUM(CASE WHEN courier_bookings.status = ? THEN courier_bookings.cod_amount ELSE 0 END) as delivered_cod', [CourierBooking::STATUS_DELIVERED])
            ->groupBy('courier_providers.id', 'courier_providers.name', 'courier_providers.driver')
            ->get()
            ->map(function ($row) {
                $completed = (int) $row->delivered + (int) $row->returned;
                $row->success_rate = $completed > 0 ? round(((int) $row->delivered / $completed) * 100, 2) : 0.0;
                $row->return_rate = $completed > 0 ? round(((int) $row->returned / $completed) * 100, 2) : 0.0;

                return $row;
            });
    }

    public function companyPerformance(): Collection
    {
        return CourierBooking::withoutGlobalScopes()
            ->join('companies', 'companies.id', '=', 'courier_bookings.company_id')
            ->select('companies.id', 'companies.name')
            ->selectRaw('COUNT(courier_bookings.id) as total')
            ->selectRaw('SUM(CASE WHEN courier_bookings.status = ? THEN 1 ELSE 0 END) as delivered', [CourierBooking::STATUS_DELIVERED])
            ->selectRaw('SUM(CASE WHEN courier_bookings.status = ? THEN 1 ELSE 0 END) as returned', [CourierBooking::STATUS_RETURNED])
            ->selectRaw('SUM(courier_bookings.cod_amount) as cod_amount')
            ->groupBy('companies.id', 'companies.name')
            ->get();
    }
}
