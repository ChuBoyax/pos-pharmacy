<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('menus')->nullable()->after('permissions');
        });

        // Existing roles keep seeing every menu their permissions allow.
        DB::table('roles')->update(['menus' => json_encode([
            'dashboard', 'pos', 'transactions', 'medicines', 'stock', 'suppliers', 'reports', 'users', 'settings',
        ])]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('menus');
        });
    }
};
