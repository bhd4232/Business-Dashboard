<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Http\Controllers\Concerns\HasTrash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreSupplierRequest;
use App\Http\Requests\Procurement\UpdateSupplierRequest;
use App\Models\Procurement\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    use HasTrash;
    public function index(Request $request): JsonResponse
    {
        $this->authorize('suppliers.view');

        $query = Supplier::with('primaryContact')
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($q) =>
                    $q->where('name_english', 'like', "%{$s}%")
                      ->orWhere('name_chinese', 'like', "%{$s}%")
                      ->orWhere('wechat_id', 'like', "%{$s}%")
                ))
            ->when($request->city, fn($q, $c) => $q->where('city', $c))
            ->when($request->boolean('active_only', false), fn($q) => $q->active())
            ->orderByDesc('created_at');

        return response()->json($query->paginate(25));
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = DB::transaction(function () use ($request) {
            $supplier = Supplier::create(
                array_merge($request->safe()->except('contacts'), ['created_by' => auth()->id()])
            );

            if ($request->contacts) {
                foreach ($request->contacts as $contact) {
                    $supplier->contacts()->create($contact);
                }
            }

            return $supplier->load('contacts');
        });

        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('suppliers.view');

        return response()->json(
            $supplier->load(['contacts', 'productSuppliers.product', 'purchaseOrders'])
        );
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return response()->json($supplier->fresh('contacts'));
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('suppliers.delete');
        return $this->softDelete($supplier, 'Supplier');
    }

    public function trashed(Request $request): JsonResponse
    {
        $this->authorize('suppliers.view');
        return response()->json(Supplier::onlyTrashed()->orderByDesc('deleted_at')->get());
    }

    public function restore(int $id): JsonResponse
    {
        $this->authorize('suppliers.edit');
        return $this->restoreModel(Supplier::onlyTrashed()->findOrFail($id), 'Supplier');
    }

    public function forceDelete(int $id): JsonResponse
    {
        return $this->purgeModel(Supplier::onlyTrashed()->findOrFail($id), 'Supplier');
    }

    public function products(Supplier $supplier): JsonResponse
    {
        $this->authorize('suppliers.view');

        return response()->json(
            $supplier->productSuppliers()->with('product.category')->get()
        );
    }

    public function orders(Supplier $supplier): JsonResponse
    {
        $this->authorize('suppliers.view');

        return response()->json(
            $supplier->purchaseOrders()->orderByDesc('created_at')->paginate(15)
        );
    }

    public function storeContact(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('suppliers.edit');

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'designation' => 'nullable|string|max:100',
            'wechat_id'   => 'nullable|string|max:100',
            'phone'       => 'nullable|string|max:20',
            'email'       => 'nullable|email|max:255',
            'is_primary'  => 'nullable|boolean',
        ]);

        if ($request->boolean('is_primary')) {
            $supplier->contacts()->update(['is_primary' => false]);
        }

        $contact = $supplier->contacts()->create($data);

        return response()->json($contact, 201);
    }

    public function destroyContact(Supplier $supplier, int $contactId): JsonResponse
    {
        $this->authorize('suppliers.edit');
        $supplier->contacts()->findOrFail($contactId)->delete();

        return response()->json(['message' => 'Contact removed.']);
    }
}
