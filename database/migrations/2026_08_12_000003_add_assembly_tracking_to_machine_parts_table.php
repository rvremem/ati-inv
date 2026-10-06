<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Who assembled a part onto a machine, and who disassembled it off again.
     *
     * Neither was recorded. Assembly (storePartForm / update / the batch import)
     * writes a machine_parts row with status 'available' and no actor at all;
     * disassembly approval was literally one line — status = 'Out' — with no
     * person and no timestamp. So "who pulled this part, and when" could not be
     * answered from the data, only guessed at from the activity log.
     *
     * All four columns are nullable: history that predates them cannot be
     * recovered, and a row that has only ever been assembled has no disassembly
     * to record.
     */
    public function up(): void
    {
        $columns = ['assembled_by', 'assembled_at', 'disassembled_by', 'disassembled_at'];
        $missing = array_filter(
            $columns,
            fn ($c) => ! Schema::connection('db_inv_ho')->hasColumn('machine_parts', $c)
        );

        if ($missing === []) {
            return;
        }

        Schema::connection('db_inv_ho')->table('machine_parts', function (Blueprint $table) use ($missing) {
            // No ->after(): sqlsrv ignores column positioning.
            //
            // No ->nullOnDelete() either, unlike requested_by. machine_parts
            // already carries one SET NULL path to users, and SQL Server rejects
            // a second: "may cause cycles or multiple cascade paths". These fall
            // back to NO ACTION, which costs nothing in practice — users are
            // soft-deleted, so the constraint only bites on a force delete.
            if (in_array('assembled_by', $missing, true)) {
                $table->foreignId('assembled_by')->nullable()->constrained('users');
            }
            if (in_array('assembled_at', $missing, true)) {
                $table->dateTime('assembled_at')->nullable();
            }
            if (in_array('disassembled_by', $missing, true)) {
                $table->foreignId('disassembled_by')->nullable()->constrained('users');
            }
            if (in_array('disassembled_at', $missing, true)) {
                $table->dateTime('disassembled_at')->nullable();
            }
        });

        // A machine_parts row is created at the moment the part goes onto the
        // machine, so created_at is exactly the assembly time — an accurate
        // backfill, not an approximation.
        DB::connection('db_inv_ho')->table('machine_parts')
            ->whereNull('assembled_at')
            ->update(['assembled_at' => DB::raw('created_at')]);

        // Best-effort, and only for rows sitting at 'Out': for those the approve
        // is the last thing that touched the row, so updated_at is the
        // disassembly time. On any other status updated_at means something else.
        DB::connection('db_inv_ho')->table('machine_parts')
            ->where('status', 'Out')
            ->whereNull('disassembled_at')
            ->update(['disassembled_at' => DB::raw('updated_at')]);

        // The actors are genuinely unrecoverable — both *_by columns stay null
        // for existing rows and render as a dash on the report.
    }

    public function down(): void
    {
        foreach ([['assembled_by', true], ['assembled_at', false], ['disassembled_by', true], ['disassembled_at', false]] as [$column, $isForeign]) {
            if (! Schema::connection('db_inv_ho')->hasColumn('machine_parts', $column)) {
                continue;
            }

            Schema::connection('db_inv_ho')->table('machine_parts', function (Blueprint $table) use ($column, $isForeign) {
                if ($isForeign) {
                    $table->dropForeign([$column]);
                }
                $table->dropColumn($column);
            });
        }
    }
};
