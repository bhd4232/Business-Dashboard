<?php

namespace App\Services\Inventory;

use App\Enums\BarcodeType;
use App\Models\Core\Product;
use App\Models\Core\ProductVariant;
use App\Models\Inventory\Barcode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BarcodeService
{
    /**
     * Fetch barcode numbering settings from id_format_settings.
     */
    public function getSettings(): array
    {
        $row = DB::table('id_format_settings')
            ->where('entity_type', 'barcode')
            ->first();

        if (! $row) {
            return $this->defaultSettings();
        }

        $settings = (array) $row;
        $settings['preview_example'] = $this->buildPreview($settings);

        return $settings;
    }

    /**
     * Update barcode numbering settings and return refreshed record.
     */
    public function updateSettings(array $data): array
    {
        $allowed = [
            'prefix', 'suffix', 'separator',
            'include_year', 'year_format',
            'include_month',
            'sequence_digits', 'sequence_start',
            'reset_annually', 'current_sequence',
        ];

        $update = array_intersect_key($data, array_flip($allowed));
        $update['updated_at'] = now();

        $exists = DB::table('id_format_settings')
            ->where('entity_type', 'barcode')
            ->exists();

        if ($exists) {
            DB::table('id_format_settings')
                ->where('entity_type', 'barcode')
                ->update($update);
        } else {
            DB::table('id_format_settings')
                ->insert(array_merge(
                    $this->defaultSettings(),
                    $update,
                    ['entity_type' => 'barcode', 'created_at' => now()]
                ));
        }

        return $this->getSettings();
    }

    /**
     * Generate a barcode value using the configured numbering format.
     * Uses a sequential counter from id_format_settings.
     */
    public function generateBarcodeValue(int $productId, ?int $variantId = null): string
    {
        return DB::transaction(function () use ($productId, $variantId) {
            $settings = DB::table('id_format_settings')
                ->where('entity_type', 'barcode')
                ->lockForUpdate()
                ->first();

            if (! $settings) {
                // Fallback to legacy pattern
                $base = 'ZAM' . str_pad($productId, 6, '0', STR_PAD_LEFT);
                if ($variantId) {
                    $base .= str_pad($variantId, 4, '0', STR_PAD_LEFT);
                }
                return $base;
            }

            $seq = (int) $settings->current_sequence;
            $code = $this->buildCode((array) $settings, $seq);

            // Advance the sequence counter
            DB::table('id_format_settings')
                ->where('entity_type', 'barcode')
                ->update([
                    'current_sequence' => $seq + 1,
                    'updated_at'       => now(),
                ]);

            return $code;
        });
    }

    /**
     * Generate QR code content for a product.
     */
    public function generateQrContent(Product $product, ?ProductVariant $variant = null): string
    {
        $data = [
            'sku'  => $variant?->sku ?? $product->sku,
            'id'   => $product->id,
            'vid'  => $variant?->id,
            'name' => $product->name,
        ];
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate or get barcode for a product/variant.
     */
    public function generateForProduct(
        int $productId,
        ?int $variantId,
        BarcodeType $type = BarcodeType::Code128
    ): Barcode {
        return DB::transaction(function () use ($productId, $variantId, $type) {
            $existing = Barcode::where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->where('type', $type)
                ->first();

            if ($existing) {
                return $existing;
            }

            $code = $type === BarcodeType::Qr
                ? $this->generateQrContent(
                    Product::findOrFail($productId),
                    $variantId ? ProductVariant::find($variantId) : null
                )
                : $this->generateBarcodeValue($productId, $variantId);

            // Ensure uniqueness
            $suffix  = 0;
            $original = $code;
            while (Barcode::where('barcode', $code)->exists()) {
                $suffix++;
                $code = $original . '-' . $suffix;
            }

            return Barcode::create([
                'product_id'         => $productId,
                'product_variant_id' => $variantId,
                'barcode'            => $code,
                'type'               => $type,
                'is_primary'         => ! Barcode::where('product_id', $productId)
                                            ->where('product_variant_id', $variantId)
                                            ->exists(),
            ]);
        });
    }

    /**
     * Bulk generate barcodes for all products without one.
     */
    public function bulkGenerateMissing(): int
    {
        $count = 0;

        Product::active()->whereDoesntHave('barcodes')->chunk(100, function ($products) use (&$count) {
            foreach ($products as $product) {
                $this->generateForProduct($product->id, null);
                $count++;

                if ($product->has_variants) {
                    foreach ($product->variants()->active()->get() as $variant) {
                        $this->generateForProduct($product->id, $variant->id);
                        $count++;
                    }
                }
            }
        });

        return $count;
    }

    /**
     * Lookup product by barcode/QR value.
     */
    public function lookupByCode(string $code): ?array
    {
        $barcode = Barcode::with(['product.category', 'variant'])
            ->where('barcode', $code)
            ->first();

        if (! $barcode) {
            return null;
        }

        return [
            'product' => $barcode->product,
            'variant' => $barcode->variant,
            'barcode' => $barcode,
        ];
    }

    // ─── Private helpers ─────────────────────────────────────────────────

    private function defaultSettings(): array
    {
        return [
            'entity_type'      => 'barcode',
            'prefix'           => 'ZAM',
            'suffix'           => '',
            'separator'        => '',
            'include_year'     => false,
            'year_format'      => 'YYYY',
            'include_month'    => false,
            'sequence_digits'  => 6,
            'sequence_start'   => 1,
            'reset_annually'   => false,
            'current_sequence' => 1,
            'preview_example'  => 'ZAM000001',
        ];
    }

    private function buildCode(array $settings, int $seq): string
    {
        $sep    = $settings['separator'] ?? '';
        $parts  = [];

        if (! empty($settings['prefix'])) {
            $parts[] = $settings['prefix'];
        }

        if (! empty($settings['include_year'])) {
            $parts[] = Carbon::now()->format('Y');
        }

        if (! empty($settings['include_month'])) {
            $parts[] = Carbon::now()->format('m');
        }

        $digits  = max(1, (int) ($settings['sequence_digits'] ?? 6));
        $parts[] = str_pad($seq, $digits, '0', STR_PAD_LEFT);

        $code = implode($sep, $parts);

        if (! empty($settings['suffix'])) {
            $code .= $settings['suffix'];
        }

        return $code;
    }

    private function buildPreview(array $settings): string
    {
        return $this->buildCode($settings, (int) ($settings['sequence_start'] ?? 1));
    }
}
