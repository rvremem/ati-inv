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
            $table->boolean('is_active')->default(true)->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->table('item_parts', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
