<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Concerns\HasTrash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCustomerRequest;
use App\Http\Requests\Sales\UpdateCustomerRequest;
use App\Models\Sales\Customer;
use App\Services\Sales\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use HasTrash;

    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('customers.view');

        $customers = Customer::with(['tags', 'priceTier'])
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($q) =>
                    $q->where('name', 'like', "%{$s}%")
                      ->orWhere('phone', 'like', "%{$s}%")
                      ->orWhere('customer_code', 'like', "%{$s}%")
                ))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->tag_id, fn($q, $tid) => $q->whereHas('tags', fn($q) => $q->where('customer_tags.id', $tid)))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->service->create($request->validated(), auth()->id());
        return response()->json($customer, 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('customers.view');

        return response()->json(
            $customer->load([
                'tags',
                'priceTier',
                'assignedSalesman',
                'salesOrders' => fn($q) => $q->latest()->limit(10),
            ])
        );
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->service->update($customer, $request->validated());
        return response()->json($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('customers.delete');
        return $this->softDelete($customer, 'Customer');
    }

    public function trashed(Request $request): JsonResponse
    {
        $this->authorize('customers.view');
        return response()->json(
            Customer::onlyTrashed()->orderByDesc('deleted_at')->get()
        );
    }

    public function restore(int $id): JsonResponse
    {
        $this->authorize('customers.edit');
        return $this->restoreModel(Customer::onlyTrashed()->findOrFail($id), 'Customer');
    }

    public function forceDelete(int $id): JsonResponse
    {
        return $this->purgeModel(Customer::onlyTrashed()->findOrFail($id), 'Customer');
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('customers.view');

        $q = $request->get('q', '');
        $customers = Customer::where('is_active', true)
            ->where(fn($query) =>
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%")
                      ->orWhere('customer_code', 'like', "%{$q}%")
            )
            ->with('priceTier')
            ->limit(20)
            ->get(['id','customer_code','name','phone','type','price_tier_id','credit_limit_bdt','outstanding_balance_bdt']);

        return response()->json($customers);
    }

    public function toggleActive(Customer $customer): JsonResponse
    {
        $this->authorize('customers.edit');
        $customer = $this->service->toggleActive($customer);
        return response()->json(['is_active' => $customer->is_active]);
    }
}
