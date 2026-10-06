<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Supplier on a part, matching how machines and receiving already carry one.
     *
     * Nullable because the catalogue predates this column — every existing part
     * has no supplier and there is no sensible value to backfill. nullOnDelete
     * rather than cascade: deleting a supplier must not delete parts.
     */
    public function up(): void
    {
        Schema::connection('db_inv_ho')->table('item_parts', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('location_id')->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->table('item_parts', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
