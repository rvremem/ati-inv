<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same problem as remarks: contract descriptions copied from the account
     * managers' spreadsheets list every covered component and run past 255
     * characters, and every such row failed the batch upload.
     */
    public function up(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->text('description')->change();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->string('description')->change();
        });
    }
};
