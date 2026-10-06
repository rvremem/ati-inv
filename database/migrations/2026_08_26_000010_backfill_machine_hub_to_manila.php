<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Puts existing machines in Manila, matching what was done for receiving.
     *
     * Every machine predates the Hub field and carries a NULL hub. Now that
     * non-administrative roles only see their own branch, a NULL hub means no
     * one but an admin can see those machines at all. Manila is the
     * head-office hub and where this equipment sat in practice.
     *
     * Only NULLs are touched, so a machine already assigned keeps its hub and
     * re-running this cannot move anything.
     */
    public function up(): void
    {
        $manila = DB::connection('db_inv_ho')->table('hub')
            ->whereRaw('LOWER(hub_name) = ?', ['manila'])
            ->value('id');

        if (! $manila) {
            return;
        }

        DB::connection('db_inv_ho')->table('machines')
            ->whereNull('hub_id')
            ->update(['hub_id' => $manila]);
    }

    public function down(): void
    {
        // Deliberately not reversed, for the same reason as the receiving
        // backfill: which rows were NULL is not recorded, so blanking every
        // Manila machine would also wipe hubs set deliberately afterwards.
    }
};
