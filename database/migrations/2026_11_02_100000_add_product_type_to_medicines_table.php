<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('product_type')->nullable()->after('category_id')->index();
        });

        // Tablets and capsules are safe to classify; anything else is left for staff to set.
        DB::table('medicines')->whereIn('unit', ['tablet', 'capsule'])->update(['product_type' => 'oral']);
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex(['product_type']);
            $table->dropColumn('product_type');
        });
    }
};
