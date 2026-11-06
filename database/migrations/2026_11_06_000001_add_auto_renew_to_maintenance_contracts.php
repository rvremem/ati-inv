<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contracts uploaded with "auto renew" in place of an end date. When one
     * reaches its end date it rolls into the next term (old end becomes the
     * new start, new end is start + coverage) instead of showing as expired —
     * see MaintenanceContract::renewExpired().
     */
    public function up(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->dropColumn('auto_renew');
        });
    }
};
