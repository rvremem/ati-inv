<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        if (!Schema::connection('db_inv_ho')->hasColumn('part_requests', 'machine_part_id')) {
            Schema::connection('db_inv_ho')->table('part_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('machine_part_id')->nullable()->after('machine_id');
                $table->foreign('machine_part_id')->references('id')->on('machine_parts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('db_inv_ho')->hasColumn('part_requests', 'machine_part_id')) {
            Schema::connection('db_inv_ho')->table('part_requests', function (Blueprint $table) {
                $table->dropForeign(['machine_part_id']);
                $table->dropColumn('machine_part_id');
            });
        }
    }
};
