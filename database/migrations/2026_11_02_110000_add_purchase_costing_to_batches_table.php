<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // Per-piece cost can be a fraction of a centavo-sized amount (₱500 / 120 = ₱4.1667).
            $table->decimal('cost', 12, 4)->default(0)->change();

            // How the purchase was made; null on batches recorded before costing existed.
            $table->unsignedInteger('packs_bought')->nullable()->after('cost');
            $table->unsignedInteger('free_packs')->default(0)->after('packs_bought');
            $table->unsignedInteger('pack_size')->default(1)->after('free_packs');
            $table->decimal('list_price', 12, 2)->nullable()->after('pack_size');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('list_price');
            $table->unsignedInteger('deal_buy')->nullable()->after('discount_percent');
            $table->unsignedInteger('deal_free')->nullable()->after('deal_buy');
            $table->decimal('total_cost', 12, 2)->nullable()->after('deal_free');
            $table->decimal('effective_discount', 5, 2)->default(0)->after('total_cost');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn([
                'packs_bought', 'free_packs', 'pack_size', 'list_price', 'discount_percent',
                'deal_buy', 'deal_free', 'total_cost', 'effective_discount',
            ]);
            $table->decimal('cost', 10, 2)->default(0)->change();
        });
    }
};
