<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a part that went out was actually used on the job.
     *
     * Set from the Used/Unused button on the Outbound report. Every request
     * starts unused, including all existing ones, until someone marks it.
     */
    public function up(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $table->boolean('is_used')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $table->dropColumn('is_used');
        });
    }
};
