<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Hub a user belongs to.
     *
     * Nullable because every existing user predates hubs and there is no
     * sensible value to backfill. nullOnDelete rather than cascade: deleting
     * a hub must not delete the users assigned to it.
     */
    public function up(): void
    {
        Schema::connection('db_inv_ho')->table('users', function (Blueprint $table) {
            $table->foreignId('hub_id')->nullable()->after('role_id')->constrained('hub')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->table('users', function (Blueprint $table) {
            $table->dropForeign(['hub_id']);
            $table->dropColumn('hub_id');
        });
    }
};
