<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = DB::table('users')->where('email', 'admin@zamzam.com')->value('id') ?? 1;

        $suppliers = [
            [
                'name_chinese'       => '深圳贸易有限公司',
                'name_english'       => 'Shenzhen Trading Co.',
                'company_name'       => 'Shenzhen Trading Ltd.',
                'wechat_id'          => 'shenzhen_trade',
                'phone'              => '+8613812345678',
                'email'              => 'info@shenzhentrade.cn',
                'city'               => 'Shenzhen',
                'province'           => 'Guangdong',
                'country'            => 'CN',
                'rating'             => 5,
                'payment_terms'      => '30% advance, 70% before shipping',
                'preferred_currency' => 'CNY',
                'is_active'          => true,
                'created_by'         => $adminId,
            ],
            [
                'name_chinese'       => '广州义乌商品城',
                'name_english'       => 'Guangzhou Yiwu Merchandise',
                'company_name'       => 'GY Merchandise Co.',
                'wechat_id'          => 'gy_merchandise',
                'phone'              => '+8613987654321',
                'city'               => 'Guangzhou',
                'province'           => 'Guangdong',
                'country'            => 'CN',
                'rating'             => 4,
                'payment_terms'      => '50% advance, 50% on shipment',
                'preferred_currency' => 'CNY',
                'is_active'          => true,
                'created_by'         => $adminId,
            ],
            [
                'name_chinese'       => '义乌小商品市场',
                'name_english'       => 'Yiwu Small Goods Market',
                'wechat_id'          => 'yiwu_goods',
                'city'               => 'Yiwu',
                'province'           => 'Zhejiang',
                'country'            => 'CN',
                'rating'             => 4,
                'payment_terms'      => '100% advance',
                'preferred_currency' => 'CNY',
                'is_active'          => true,
                'created_by'         => $adminId,
            ],
        ];

        foreach ($suppliers as $supplier) {
            $exists = DB::table('suppliers')
                ->where('name_english', $supplier['name_english'])
                ->exists();

            if (! $exists) {
                $id = DB::table('suppliers')->insertGetId(array_merge($supplier, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                // Add primary contact for each supplier
                DB::table('supplier_contacts')->insert([
                    'supplier_id' => $id,
                    'name'        => 'Primary Contact',
                    'wechat_id'   => $supplier['wechat_id'],
                    'is_primary'  => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }
    }
}
