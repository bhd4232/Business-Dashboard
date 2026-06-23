<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\CourierStatusLog;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CourierService
{
    public function manualProvider(?Company $company = null): CourierProvider
    {
        $company ??= app(CompanyContext::class)->company();
        $company ??= Company::defaultCompany();

        if (! $company) {
            throw ValidationException::withMessages([
                'company' => 'A company is required before creating a courier booking.',
            ]);
        }

        return CourierProvider::query()->firstOrCreate(
            [
                'company_id' => $company->getKey(),
                'slug' => 'manual',
            ],
            [
                'name' => 'Manual Courier',
                'driver' => CourierProvider::DRIVER_MANUAL,
                'credentials' => [],
                'settings' => [],
                'is_active' => true,
            ],
        );
    }

    public function createManualBooking(Order $order, array $data = []): CourierBooking
    {
        $order->loadMissing('customer', 'company');

        if (! $order->company_id) {
            throw ValidationException::withMessages([
                'order' => 'The order must belong to a company before booking courier.',
            ]);
        }

        $provider = filled($data['courier_provider_id'] ?? null)
            ? CourierProvider::query()->whereKey($data['courier_provider_id'])->firstOrFail()
            : $this->manualProvider($order->company);

        if ($provider->driver !== CourierProvider::DRIVER_MANUAL) {
            throw ValidationException::withMessages([
                'provider' => 'Please select a custom/manual courier provider.',
            ]);
        }

        if ((int) $provider->company_id !== (int) $order->company_id) {
            throw ValidationException::withMessages([
                'provider' => 'The courier provider must belong to the same company as the order.',
            ]);
        }

        $status = filled($data['tracking_id'] ?? null)
            ? CourierBooking::STATUS_BOOKED
            : CourierBooking::STATUS_BOOKING_PENDING;

        $booking = CourierBooking::query()->create([
            'company_id' => $order->company_id,
            'courier_provider_id' => $provider->getKey(),
            'order_id' => $order->getKey(),
            'tracking_id' => $data['tracking_id'] ?? $this->manualTrackingId($order),
            'recipient_name' => $data['recipient_name'] ?? $order->customer_name ?? $order->customer?->name ?? 'Customer',
            'recipient_phone' => $data['recipient_phone'] ?? $order->customer?->phone,
            'recipient_address' => $data['recipient_address'] ?? $order->customer?->address,
            'cod_amount' => $data['cod_amount'] ?? $order->due_amount ?? 0,
            'status' => $status,
            'booked_at' => now(),
            'note' => $data['note'] ?? null,
        ]);

        $this->logStatus($booking, null, $status, $data['note'] ?? 'Manual courier booking created.');
        $order->forceFill(['delivery_status' => $status])->saveQuietly();

        return $booking;
    }

    public function createSteadfastBooking(Order $order, CourierProvider $provider, array $data = []): CourierBooking
    {
        $order->loadMissing(['customer', 'items.product', 'company']);

        if ($provider->driver !== CourierProvider::DRIVER_STEADFAST) {
            throw ValidationException::withMessages([
                'provider' => 'Please select a Steadfast courier provider.',
            ]);
        }

        if ((int) $provider->company_id !== (int) $order->company_id) {
            throw ValidationException::withMessages([
                'provider' => 'The courier provider must belong to the same company as the order.',
            ]);
        }

        $payload = $this->steadfastPayload($order, $data);
        $response = app(SteadfastCourierClient::class)->createOrder($provider, $payload);
        $consignment = $response['consignment'] ?? [];

        if (($response['status'] ?? null) !== 200 || blank($consignment['tracking_code'] ?? null)) {
            throw ValidationException::withMessages([
                'steadfast' => $response['message'] ?? 'Steadfast order creation failed.',
            ]);
        }

        $status = $this->normalizeSteadfastStatus($consignment['status'] ?? 'in_review');
        $booking = CourierBooking::query()->create([
            'company_id' => $order->company_id,
            'courier_provider_id' => $provider->getKey(),
            'order_id' => $order->getKey(),
            'tracking_id' => $consignment['tracking_code'],
            'provider_reference' => (string) ($consignment['consignment_id'] ?? ''),
            'recipient_name' => $consignment['recipient_name'] ?? $payload['recipient_name'],
            'recipient_phone' => $consignment['recipient_phone'] ?? $payload['recipient_phone'],
            'recipient_address' => $consignment['recipient_address'] ?? $payload['recipient_address'],
            'cod_amount' => $consignment['cod_amount'] ?? $payload['cod_amount'],
            'status' => $status,
            'booked_at' => now(),
            'note' => $payload['note'] ?? null,
        ]);

        $this->logStatus($booking, null, $status, $response['message'] ?? 'Steadfast consignment created.');
        $order->forceFill(['delivery_status' => $status])->saveQuietly();

        return $booking;
    }

    public function syncSteadfastStatus(CourierBooking $booking): CourierBooking
    {
        $booking->loadMissing('provider', 'order');

        if ($booking->provider?->driver !== CourierProvider::DRIVER_STEADFAST) {
            throw ValidationException::withMessages([
                'provider' => 'Only Steadfast bookings can be synced with Steadfast.',
            ]);
        }

        $response = filled($booking->tracking_id)
            ? app(SteadfastCourierClient::class)->statusByTrackingCode($booking->provider, $booking->tracking_id)
            : app(SteadfastCourierClient::class)->statusByInvoice($booking->provider, $booking->order->order_number);

        if (($response['status'] ?? null) !== 200 || blank($response['delivery_status'] ?? null)) {
            throw ValidationException::withMessages([
                'steadfast' => 'Unable to sync Steadfast delivery status.',
            ]);
        }

        return $this->updateStatus(
            $booking,
            $this->normalizeSteadfastStatus($response['delivery_status']),
            'Synced from Steadfast: '.$response['delivery_status'],
        );
    }

    public function updateStatus(CourierBooking $booking, string $status, ?string $note = null): CourierBooking
    {
        if (! array_key_exists($status, CourierBooking::STATUSES)) {
            throw ValidationException::withMessages([
                'status' => 'Please select a valid courier status.',
            ]);
        }

        $fromStatus = $booking->status;

        $booking->forceFill([
            'status' => $status,
            'delivered_at' => $status === CourierBooking::STATUS_DELIVERED ? now() : $booking->delivered_at,
            'returned_at' => $status === CourierBooking::STATUS_RETURNED ? now() : $booking->returned_at,
            'note' => $note ?? $booking->note,
        ])->save();

        $this->logStatus($booking, $fromStatus, $status, $note);
        $booking->order?->forceFill(['delivery_status' => $status])->saveQuietly();

        return $booking->refresh();
    }

    protected function manualTrackingId(Order $order): string
    {
        return 'MAN-'.$order->getKey().'-'.now()->format('YmdHis');
    }

    protected function steadfastPayload(Order $order, array $data): array
    {
        $itemDescription = $data['item_description'] ?? $order->items
            ->map(fn ($item): string => trim(($item->product?->name ?? 'Item').' x '.$item->quantity))
            ->filter()
            ->implode(', ');

        return array_filter([
            'invoice' => $order->order_number,
            'recipient_name' => $data['recipient_name'] ?? $order->customer_name ?? $order->customer?->name,
            'recipient_phone' => $data['recipient_phone'] ?? $order->customer?->phone,
            'alternative_phone' => $data['alternative_phone'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? $order->customer?->email,
            'recipient_address' => $data['recipient_address'] ?? $order->customer?->address,
            'cod_amount' => $data['cod_amount'] ?? $order->due_amount ?? 0,
            'note' => $data['note'] ?? $order->note,
            'item_description' => $itemDescription,
            'total_lot' => $data['total_lot'] ?? $order->items->sum('quantity'),
            'delivery_type' => $data['delivery_type'] ?? 0,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    public function normalizeSteadfastStatus(string $status): string
    {
        return match ($status) {
            'delivered', 'delivered_approval_pending' => CourierBooking::STATUS_DELIVERED,
            'partial_delivered', 'partial_delivered_approval_pending' => CourierBooking::STATUS_PARTIAL_DELIVERED,
            'cancelled', 'cancelled_approval_pending' => CourierBooking::STATUS_CANCELLED,
            'in_review' => CourierBooking::STATUS_BOOKING_PENDING,
            'hold', 'pending' => CourierBooking::STATUS_IN_TRANSIT,
            'unknown', 'unknown_approval_pending' => CourierBooking::STATUS_FAILED,
            default => CourierBooking::STATUS_BOOKED,
        };
    }

    protected function logStatus(CourierBooking $booking, ?string $fromStatus, string $toStatus, ?string $note = null): void
    {
        CourierStatusLog::query()->create([
            'company_id' => $booking->company_id,
            'courier_booking_id' => $booking->getKey(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'created_by' => Auth::id(),
        ]);
    }
}
