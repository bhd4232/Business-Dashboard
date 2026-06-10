<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Enums\BarcodeType;
use App\Http\Controllers\Controller;
use App\Models\Inventory\Barcode;
use App\Services\Inventory\BarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    public function __construct(private BarcodeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('inventory.view');

        $query = Barcode::with(['product', 'variant'])
            ->when($request->product_id, fn($q, $p) => $q->where('product_id', $p))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->orderByDesc('created_at');

        return response()->json($query->paginate(50));
    }

    public function generate(Request $request): JsonResponse
    {
        $this->authorize('inventory.adjust');

        $data = $request->validate([
            'product_id'         => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'type'               => 'nullable|in:ean13,code128,qr,custom',
        ]);

        $type    = BarcodeType::from($data['type'] ?? 'code128');
        $barcode = $this->service->generateForProduct(
            $data['product_id'],
            $data['product_variant_id'] ?? null,
            $type
        );

        return response()->json($barcode, 201);
    }

    public function bulkGenerate(): JsonResponse
    {
        $this->authorize('inventory.adjust');

        $count = $this->service->bulkGenerateMissing();

        return response()->json(['generated' => $count]);
    }

    public function scan(Request $request): JsonResponse
    {
        $this->authorize('inventory.view');

        $data = $request->validate(['code' => 'required|string|max:100']);

        $result = $this->service->lookupByCode($data['code']);

        if (! $result) {
            return response()->json(['message' => 'Barcode not found.'], 404);
        }

        return response()->json($result);
    }

    /**
     * Get barcode numbering sequence settings.
     */
    public function getSettings(): JsonResponse
    {
        $this->authorize('inventory.view');

        return response()->json($this->service->getSettings());
    }

    /**
     * Update barcode numbering sequence settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $this->authorize('inventory.adjust');

        $data = $request->validate([
            'prefix'           => 'required|string|max:10',
            'suffix'           => 'nullable|string|max:10',
            'separator'        => 'nullable|string|max:5',
            'include_year'     => 'boolean',
            'year_format'      => 'nullable|string|in:YYYY,YY',
            'include_month'    => 'boolean',
            'sequence_digits'  => 'required|integer|min:1|max:12',
            'sequence_start'   => 'required|integer|min:1',
            'reset_annually'   => 'boolean',
            'current_sequence' => 'required|integer|min:1',
        ]);

        $settings = $this->service->updateSettings($data);

        return response()->json($settings);
    }
}
