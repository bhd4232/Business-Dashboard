<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\Order;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderTrackController extends Controller
{
    public function __construct(protected CompanyContext $context) {}

    public function index(Request $request): View|RedirectResponse
    {
        [$company, $setting] = $this->domainStorefront($request);

        if ($request->filled('order_number')) {
            return redirect()->route('storefront.track.show', trim((string) $request->string('order_number')));
        }

        return $this->trackView($company, $setting);
    }

    public function indexPreview(Request $request, Company $company): View|RedirectResponse
    {
        $setting = $this->previewStorefront($company);

        if ($request->filled('order_number')) {
            return redirect()->route('storefront.preview.track.show', [
                $company->slug,
                trim((string) $request->string('order_number')),
            ]);
        }

        return $this->trackView($company, $setting, previewSlug: $company->slug);
    }

    public function show(Request $request, string $orderNo): View
    {
        [$company, $setting] = $this->domainStorefront($request);

        return $this->trackView($company, $setting, $this->storefrontOrder($company, $orderNo));
    }

    public function showPreview(Company $company, string $orderNo): View
    {
        $setting = $this->previewStorefront($company);

        return $this->trackView(
            company: $company,
            setting: $setting,
            order: $this->storefrontOrder($company, $orderNo),
            previewSlug: $company->slug,
        );
    }

    protected function trackView(
        Company $company,
        StorefrontSetting $setting,
        ?Order $order = null,
        ?string $previewSlug = null,
    ): View {
        $order?->load(['customer', 'items.product', 'latestCourierBooking.provider', 'latestCourierBooking.statusLogs']);

        return view('storefront.track.show', [
            'company' => $company,
            'setting' => $setting,
            'previewSlug' => $previewSlug,
            'order' => $order,
            'trackingUpdates' => $order ? $this->trackingUpdates($order) : collect(),
        ]);
    }

    protected function trackingUpdates(Order $order)
    {
        $auditUpdates = AuditLog::query()
            ->where('auditable_type', Order::class)
            ->where('auditable_id', $order->getKey())
            ->where('action', 'updated')
            ->latest()
            ->get()
            ->flatMap(function (AuditLog $log): array {
                $updates = [];

                if (array_key_exists('delivery_status', $log->new_values ?? [])) {
                    $updates[] = [
                        'time' => $log->created_at,
                        'type' => 'delivery',
                        'title' => 'Delivery status updated',
                        'message' => 'Delivery status updated to '.(Order::DELIVERY_STATUSES[$log->new_values['delivery_status']] ?? str((string) $log->new_values['delivery_status'])->headline()).'.',
                    ];
                }

                if (array_key_exists('status', $log->new_values ?? [])) {
                    $updates[] = [
                        'time' => $log->created_at,
                        'type' => 'order',
                        'title' => 'Order status updated',
                        'message' => 'Order status updated to '.(Order::STATUSES[$log->new_values['status']] ?? str((string) $log->new_values['status'])->headline()).'.',
                    ];
                }

                return $updates;
            });

        $courierUpdates = $order->latestCourierBooking?->statusLogs
            ->map(fn ($log): array => [
                'time' => $log->created_at,
                'type' => 'courier',
                'title' => 'Courier status updated',
                'message' => trim((CourierBooking::STATUSES[$log->to_status] ?? str((string) $log->to_status)->headline()).($log->note ? ': '.$log->note : '.')),
            ]) ?? collect();

        return $auditUpdates
            ->merge($courierUpdates)
            ->sortByDesc('time')
            ->values();
    }

    protected function storefrontOrder(Company $company, string $orderNo): Order
    {
        return Order::query()
            ->where('company_id', $company->getKey())
            ->where('source', Order::SOURCE_STOREFRONT)
            ->where('order_number', trim($orderNo))
            ->firstOrFail();
    }

    protected function domainStorefront(Request $request): array
    {
        $company = $request->attributes->get('storefront_company');

        abort_unless($company instanceof Company && $company->storefrontSetting?->is_published, 404);

        $this->context->set($company);

        return [$company, $company->storefrontSetting];
    }

    protected function previewStorefront(Company $company): StorefrontSetting
    {
        abort_unless(app()->environment(['local', 'testing']) || auth()->check(), 404);

        $this->context->set($company);

        return $company->storefrontSetting ?: new StorefrontSetting([
            'company_id' => $company->getKey(),
            'theme_color' => '#F59E0B',
            'meta_title' => $company->name,
            'is_published' => true,
        ]);
    }
}
