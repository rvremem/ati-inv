<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 255 characters is not enough for remarks the item stock batch upload
     * writes: a client cell listing several clients is kept there in full,
     * and rows summed into one receipt join their remarks together. A row
     * over the limit failed to save outright.
     */
    public function up(): void
    {
        Schema::table('receiving', function (Blueprint $table) {
            $table->text('return_remarks')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('receiving', function (Blueprint $table) {
            $table->string('return_remarks')->nullable()->change();
        });
    }
};
