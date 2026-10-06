<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * When a machine part was requested out.
     *
     * Same gap the requested_by migration closed, and for the same reason: a
     * machine-part request is only a status flip to 'Pending Out' on the
     * existing row, so nothing recorded when it happened. created_at is when
     * the part was installed on the machine, which can be months earlier, and
     * updated_at is overwritten by any later edit — neither can be trusted as
     * the request date.
     *
     * Nullable because rows requested before this column existed have no
     * request time to recover; those show a dash on Pending Approvals.
     */
    public function up(): void
    {
        if (Schema::connection('db_inv_ho')->hasColumn('machine_parts', 'requested_at')) {
            return;
        }

        Schema::connection('db_inv_ho')->table('machine_parts', function (Blueprint $table) {
            // No ->after(): sqlsrv ignores column positioning.
            $table->dateTime('requested_at')->nullable();
        });

        // Best-effort backfill for requests already waiting when this runs. For
        // a row still sitting at 'Pending Out', the status flip is the last
        // thing that touched it, so updated_at is the request time. Deliberately
        // limited to those rows: on any other status updated_at means something
        // else entirely.
        DB::connection('db_inv_ho')->table('machine_parts')
            ->where('status', 'Pending Out')
            ->whereNull('requested_at')
            ->update(['requested_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        if (Schema::connection('db_inv_ho')->hasColumn('machine_parts', 'requested_at')) {
            Schema::connection('db_inv_ho')->table('machine_parts', function (Blueprint $table) {
                $table->dropColumn('requested_at');
            });
        }
    }
};
