<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries that run on every dashboard, stock and POS load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // Expiring / expired stock across all medicines (dashboard, stock filters, alerts).
            $table->index(['expiry_date', 'quantity']);
        });

        Schema::table('medicines', function (Blueprint $table) {
            // Catalogue lists are always sorted by name, usually limited to active items.
            $table->index(['is_active', 'name']);
            $table->index('name');
        });

        Schema::table('users', function (Blueprint $table) {
            // Users are joined to roles by name and filtered by role.
            $table->index('role');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            // Best sellers group by product name.
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('batches', fn (Blueprint $table) => $table->dropIndex(['expiry_date', 'quantity']));
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'name']);
            $table->dropIndex(['name']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['role']));
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropIndex(['name']));
    }
};
