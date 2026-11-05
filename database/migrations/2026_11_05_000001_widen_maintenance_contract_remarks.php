<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contract remarks from the account managers' spreadsheets carry the whole
     * support terms (response times, contact numbers, escalation steps), well
     * past 255 characters. Every such row failed the batch upload with
     * "String or binary data would be truncated".
     */
    public function up(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->text('remarks')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->string('remarks')->nullable()->change();
        });
    }
};
