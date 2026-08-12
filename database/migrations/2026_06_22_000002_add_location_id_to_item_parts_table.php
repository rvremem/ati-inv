<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        Schema::connection('db_inv_ho')->table('item_parts', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('is_active')->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->table('item_parts', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
