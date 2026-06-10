<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IdFormatSeeder extends Seeder
{
    public function run(): void
    {
        $formats = [
            [
                'entity_type'     => 'customer',
                'prefix'          => 'C',
                'separator'       => '-',
                'include_year'    => false,
                'sequence_digits' => 6,
                'preview_example' => 'C-000001',
            ],
            [
                'entity_type'     => 'sales_order',
                'prefix'          => 'SO',
                'separator'       => '-',
                'include_year'    => true,
                'year_format'     => 'YYYY',
                'sequence_digits' => 4,
                'reset_annually'  => true,
                'preview_example' => 'SO-2026-0001',
            ],
            [
                'entity_type'     => 'invoice',
                'prefix'          => 'INV',
                'separator'       => '-',
                'include_year'    => true,
                'year_format'     => 'YYYY',
                'sequence_digits' => 4,
                'reset_annually'  => true,
                'preview_example' => 'INV-2026-0001',
            ],
            [
                'entity_type'     => 'purchase_order',
                'prefix'          => 'PO',
                'separator'       => '-',
                'include_year'    => true,
                'year_format'     => 'YYYY',
                'sequence_digits' => 4,
                'reset_annually'  => true,
                'preview_example' => 'PO-2026-0001',
            ],
            [
                'entity_type'     => 'shipment',
                'prefix'          => 'SH',
                'separator'       => '-',
                'include_year'    => true,
                'year_format'     => 'YYYY',
                'sequence_digits' => 3,
                'reset_annually'  => true,
                'preview_example' => 'SH-2026-001',
            ],
            [
                'entity_type'     => 'parcel',
                'prefix'          => 'PKG',
                'separator'       => '-',
                'include_year'    => false,
                'sequence_digits' => 6,
                'preview_example' => 'PKG-000001',
            ],
            [
                'entity_type'     => 'barcode',
                'prefix'          => 'ZAM',
                'suffix'          => '',
                'separator'       => '',
                'include_year'    => false,
                'include_month'   => false,
                'sequence_digits' => 6,
                'sequence_start'  => 1,
                'reset_annually'  => false,
                'current_sequence'=> 1,
                'preview_example' => 'ZAM000001',
            ],
        ];

        foreach ($formats as $format) {
            DB::table('id_format_settings')->updateOrInsert(
                ['entity_type' => $format['entity_type']],
                array_merge([
                    'suffix'           => '',
                    'separator'        => '-',
                    'include_year'     => false,
                    'year_format'      => 'YYYY',
                    'include_month'    => false,
                    'sequence_digits'  => 4,
                    'sequence_start'   => 1,
                    'reset_annually'   => false,
                    'current_sequence' => 1,
                ], $format, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
