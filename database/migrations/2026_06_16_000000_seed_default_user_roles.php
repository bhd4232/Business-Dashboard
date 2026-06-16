<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_roles')) {
            return;
        }

        $now = now();

        foreach (User::ROLES as $slug => $name) {
            $values = [
                'name' => $name,
                'permissions' => json_encode(User::ROLE_PERMISSIONS[$slug] ?? []),
                'is_active' => true,
                'updated_at' => $now,
            ];

            $exists = DB::table('user_roles')->where('slug', $slug)->exists();

            if ($exists) {
                DB::table('user_roles')
                    ->where('slug', $slug)
                    ->update($values);

                continue;
            }

            DB::table('user_roles')->insert([
                ...$values,
                'slug' => $slug,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
