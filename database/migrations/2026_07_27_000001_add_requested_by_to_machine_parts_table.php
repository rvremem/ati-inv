<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Machine-part requests were recorded only as a status flip on machine_parts
     * ('Pending Out'), with no record of who submitted them — so they could not
     * appear on the requester's "My Requests" page. This stores the requester.
     *
     * Nullable because rows requested before this migration have no requester to
     * backfill; those simply won't show up under anyone's My Requests.
     */
    public function up(): void
    {
        if (!Schema::connection('db_inv_ho')->hasColumn('machine_parts', 'requested_by')) {
            Schema::connection('db_inv_ho')->table('machine_parts', function (Blueprint $table) {
                // No ->after(): sqlsrv ignores column positioning.
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('db_inv_ho')->hasColumn('machine_parts', 'requested_by')) {
            Schema::connection('db_inv_ho')->table('machine_parts', function (Blueprint $table) {
                $table->dropForeign(['requested_by']);
                $table->dropColumn('requested_by');
            });
        }
    }
};
