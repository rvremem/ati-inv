<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Hub a machine belongs to, alongside the existing location and supplier.
     *
     * Nullable because existing machines predate hubs. nullOnDelete rather
     * than cascade: deleting a hub must not delete machines.
     */
    public function up(): void
    {
        Schema::connection('db_inv_ho')->table('machines', function (Blueprint $table) {
            $table->foreignId('hub_id')->nullable()->after('location_id')->constrained('hub')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->table('machines', function (Blueprint $table) {
            $table->dropForeign(['hub_id']);
            $table->dropColumn('hub_id');
        });
    }
};
