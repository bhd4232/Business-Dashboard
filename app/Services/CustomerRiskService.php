<?php

namespace App\Services;

use App\Models\CourierBooking;
use App\Models\Customer;
use App\Models\CustomerBlacklist;
use App\Models\CustomerRiskEvent;
use App\Models\CustomerRiskProfile;
use App\Models\CustomerRiskReview;
use App\Models\FraudCheck;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CustomerRiskService
{
    public const HIGH_COD_AMOUNT = 5000;

    public function evaluateCustomer(Customer $customer, ?Order $order = null): CustomerRiskProfile
    {
        $settings = app(CustomerRiskSettingsService::class);
        $phone = $this->normalizePhone($customer->phone);
        $bookings = CourierBooking::query()->whereHas('order', fn ($query) => $query->where('customer_id', $customer->getKey()));
        $total = (clone $bookings)->count();
        $delivered = (clone $bookings)->where('status', CourierBooking::STATUS_DELIVERED)->count();
        $returned = (clone $bookings)->where('status', CourierBooking::STATUS_RETURNED)->count();
        $cancelled = (clone $bookings)->where('status', CourierBooking::STATUS_CANCELLED)->count();
        $successRatio = $this->ratio($delivered, $total);
        $returnRatio = $this->ratio($returned, $total);
        $cancelRatio = $this->ratio($cancelled, $total);
        $factors = [];

        if ($returnRatio > $settings->int('high_return_ratio_threshold')) {
            $factors['high_return_ratio'] = ['deduction' => $settings->int('high_return_ratio_deduction'), 'message' => 'Return ratio is above '.$settings->int('high_return_ratio_threshold').'%.'];
        }
        if ($total > $settings->int('low_success_total_orders') && $successRatio < $settings->int('low_success_ratio_threshold')) {
            $factors['low_success_ratio'] = ['deduction' => $settings->int('low_success_ratio_deduction'), 'message' => 'Courier order success ratio is below '.$settings->int('low_success_ratio_threshold').'%.'];
        }
        if ($phone && Customer::withoutGlobalScopes()->where('phone', $customer->phone)->whereKeyNot($customer->getKey())->where('name', '!=', $customer->name)->exists()) {
            $factors['phone_multiple_names'] = ['deduction' => $settings->int('phone_multiple_names_deduction'), 'message' => 'The phone number is used by multiple customer names.'];
        }
        if ($order && $customer->orders()->whereKeyNot($order->getKey())->count() === 0 && (float) $order->due_amount >= $settings->int('high_cod_amount')) {
            $factors['high_cod_first_order'] = ['deduction' => $settings->int('high_cod_first_order_deduction'), 'message' => 'First order has a high COD amount.'];
        }
        if (mb_strlen(trim((string) ($order?->customer?->address ?? $customer->address))) < 10) {
            $factors['incomplete_address'] = ['deduction' => $settings->int('incomplete_address_deduction'), 'message' => 'Address appears incomplete.'];
        }
        if ($order && $this->hasRecentDuplicate($order)) {
            $factors['recent_duplicate_order'] = ['deduction' => $settings->int('recent_duplicate_order_deduction'), 'message' => 'A similar customer order was created in the last 24 hours.'];
        }
        if ($cancelled >= 2) {
            $factors['repeated_cancellation'] = ['deduction' => $settings->int('repeated_cancellation_deduction'), 'message' => 'Two or more courier bookings were cancelled.'];
        }

        $blacklisted = $this->blacklistMatch($customer);
        if ($blacklisted) {
            $factors['blacklist_match'] = ['deduction' => $settings->int('blacklist_match_deduction'), 'message' => 'Phone or address matches an active blacklist entry.'];
        }

        $score = max(0, 100 - collect($factors)->sum('deduction'));
        $level = $blacklisted ? CustomerRiskProfile::LEVEL_BLACKLISTED : $this->level($score);

        return CustomerRiskProfile::query()->updateOrCreate(
            ['company_id' => $customer->company_id, 'phone' => $phone ?: 'customer-'.$customer->getKey()],
            [
                'customer_id' => $customer->getKey(),
                'total_courier_orders' => $total,
                'delivered_orders' => $delivered,
                'returned_orders' => $returned,
                'cancelled_orders' => $cancelled,
                'success_ratio' => $successRatio,
                'return_ratio' => $returnRatio,
                'cancel_ratio' => $cancelRatio,
                'risk_score' => $score,
                'risk_level' => $level,
                'is_blacklisted' => (bool) $blacklisted,
                'factors' => $factors,
                'evaluated_at' => now(),
            ],
        );
    }

    public function evaluateOrder(Order $order): FraudCheck
    {
        $order->loadMissing('customer');
        if (! $order->customer) {
            throw ValidationException::withMessages(['customer' => 'A customer is required for risk evaluation.']);
        }

        $profile = $this->evaluateCustomer($order->customer, $order);

        return FraudCheck::query()->create([
            'company_id' => $order->company_id,
            'order_id' => $order->getKey(),
            'customer_id' => $order->customer_id,
            'phone' => $profile->phone,
            'risk_score' => $profile->risk_score,
            'risk_level' => $profile->risk_level,
            'factors' => $profile->factors,
            'is_blacklisted' => $profile->is_blacklisted,
            'checked_by' => Auth::id(),
        ]);
    }

    public function recordDeliveryEvent(Order $order, string $status): void
    {
        if (! in_array($status, [CourierBooking::STATUS_DELIVERED, CourierBooking::STATUS_RETURNED, CourierBooking::STATUS_CANCELLED], true) || ! $order->customer) {
            return;
        }

        $profile = $this->evaluateCustomer($order->customer, $order);
        CustomerRiskEvent::query()->firstOrCreate(
            ['order_id' => $order->getKey(), 'event_type' => 'order_'.$status],
            ['company_id' => $order->company_id, 'customer_risk_profile_id' => $profile->getKey(), 'customer_id' => $order->customer_id, 'metadata' => ['delivery_status' => $status]],
        );
    }

    public function assertNotBlacklisted(Order $order): CustomerRiskProfile
    {
        return $this->assertCourierBookingAllowed($order);
    }

    public function assertCourierBookingAllowed(Order $order): CustomerRiskProfile
    {
        $order->loadMissing('customer');
        if (! $order->customer) {
            throw ValidationException::withMessages(['customer' => 'A customer is required before courier booking.']);
        }
        $profile = $this->evaluateCustomer($order->customer, $order);

        if ($this->requiresApproval($profile)) {
            $review = $this->requestReview($order, $profile);

            if ($review->status !== CustomerRiskReview::STATUS_APPROVED) {
                $type = CustomerRiskReview::TYPES[$review->approval_type] ?? 'Approval';

                throw ValidationException::withMessages(['risk' => "Courier booking is blocked because this order requires {$type}. Review request #{$review->getKey()} is {$review->status}."]);
            }
        }

        return $profile;
    }

    public function requiresApproval(CustomerRiskProfile $profile): bool
    {
        return in_array($profile->risk_level, [CustomerRiskProfile::LEVEL_HIGH, CustomerRiskProfile::LEVEL_BLACKLISTED], true);
    }

    public function requestReview(Order $order, CustomerRiskProfile $profile): CustomerRiskReview
    {
        $latestCheck = $order->latestFraudCheck()->first();
        $type = $profile->risk_level === CustomerRiskProfile::LEVEL_BLACKLISTED
            ? CustomerRiskReview::TYPE_OWNER
            : CustomerRiskReview::TYPE_MANAGER;

        $approved = CustomerRiskReview::query()
            ->where('order_id', $order->getKey())
            ->where('approval_type', $type)
            ->where('status', CustomerRiskReview::STATUS_APPROVED)
            ->latest('id')
            ->first();

        if ($approved) {
            return $approved;
        }

        return CustomerRiskReview::query()->firstOrCreate(
            [
                'order_id' => $order->getKey(),
                'approval_type' => $type,
                'status' => CustomerRiskReview::STATUS_PENDING,
            ],
            [
                'company_id' => $order->company_id,
                'customer_id' => $order->customer_id,
                'fraud_check_id' => $latestCheck?->getKey(),
                'risk_level' => $profile->risk_level,
                'risk_score' => $profile->risk_score,
                'reason' => collect($profile->factors ?? [])->pluck('message')->filter()->implode(' '),
                'requested_by' => Auth::id(),
            ],
        );
    }

    public function approveReview(CustomerRiskReview $review, ?string $note = null): CustomerRiskReview
    {
        return $this->completeReview($review, CustomerRiskReview::STATUS_APPROVED, $note);
    }

    public function rejectReview(CustomerRiskReview $review, ?string $note = null): CustomerRiskReview
    {
        return $this->completeReview($review, CustomerRiskReview::STATUS_REJECTED, $note);
    }

    public function level(int $score): string
    {
        return $score >= 80 ? CustomerRiskProfile::LEVEL_LOW : ($score >= 50 ? CustomerRiskProfile::LEVEL_MEDIUM : CustomerRiskProfile::LEVEL_HIGH);
    }

    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (! $digits) {
            return null;
        }
        if (str_starts_with($digits, '01')) {
            $digits = '88'.$digits;
        }

        return '+'.$digits;
    }

    protected function ratio(int $count, int $total): float
    {
        return $total > 0 ? round(($count / $total) * 100, 2) : 0.0;
    }

    protected function completeReview(CustomerRiskReview $review, string $status, ?string $note = null): CustomerRiskReview
    {
        $review->forceFill([
            'status' => $status,
            'review_note' => $note,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ])->save();

        return $review->refresh();
    }

    protected function hasRecentDuplicate(Order $order): bool
    {
        return Order::query()->where('customer_id', $order->customer_id)->whereKeyNot($order->getKey())
            ->where('created_at', '>=', now()->subDay())->where('total_amount', $order->total_amount)->exists();
    }

    protected function blacklistMatch(Customer $customer): ?CustomerBlacklist
    {
        $phone = $this->normalizePhone($customer->phone);

        if (! $phone && blank($customer->address)) {
            return null;
        }

        return CustomerBlacklist::query()->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $customer->company_id))
            ->where(function ($query) use ($phone, $customer): void {
                $query->when($phone, fn ($q) => $q->where('phone', $phone));
                if (filled($customer->address)) {
                    $query->orWhereRaw('LOWER(address) = ?', [mb_strtolower(trim($customer->address))]);
                }
            })->first();
    }
}
