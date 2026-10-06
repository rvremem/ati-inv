<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Marks which hub is head office.
     *
     * Defective items and maintenance contracts are handled centrally rather
     * than per branch, so they are visible to head office and to the
     * administrative roles, and to nobody else.
     *
     * A flag on the row rather than a hardcoded 'Manila' in the code: head
     * office can be renamed or moved without touching a scope, and more than
     * one hub can hold the role if that ever becomes true.
     */
    public function up(): void
    {
        Schema::connection('db_inv_ho')->table('hub', function (Blueprint $table) {
            $table->boolean('is_head_office')->default(false);
        });

        DB::connection('db_inv_ho')->table('hub')
            ->whereRaw('LOWER(hub_name) = ?', ['manila'])
            ->update(['is_head_office' => true]);
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->table('hub', function (Blueprint $table) {
            $table->dropColumn('is_head_office');
        });
    }
};
