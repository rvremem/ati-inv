<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        Schema::connection('db_inv_ho')->table('machine_parts', function (Blueprint $table) {
            if (!Schema::connection('db_inv_ho')->hasColumn('machine_parts', 'status')) {
                $table->string('status')->default('available')->after('purpose');
            }
            if (!Schema::connection('db_inv_ho')->hasColumn('machine_parts', 'remarks')) {
                $table->string('remarks')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->table('machine_parts', function (Blueprint $table) {
            $cols = [];
            if (Schema::connection('db_inv_ho')->hasColumn('machine_parts', 'status')) {
                $cols[] = 'status';
            }
            if (Schema::connection('db_inv_ho')->hasColumn('machine_parts', 'remarks')) {
                $cols[] = 'remarks';
            }
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
