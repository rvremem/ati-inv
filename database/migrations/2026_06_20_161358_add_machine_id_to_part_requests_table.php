<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        if (!Schema::connection('db_inv_ho')->hasColumn('part_requests', 'machine_id')) {
            Schema::connection('db_inv_ho')->table('part_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('machine_id')->nullable()->after('customer_id');
                $table->foreign('machine_id')->references('id')->on('machines')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('db_inv_ho')->hasColumn('part_requests', 'machine_id')) {
            Schema::connection('db_inv_ho')->table('part_requests', function (Blueprint $table) {
                $table->dropForeign(['machine_id']);
                $table->dropColumn('machine_id');
            });
        }
    }
};
