<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Puts existing stock in Manila.
     *
     * Every receiving row predates the Hub field, so all of them carry a NULL
     * hub. Stock Levels is filtered by hub now, which would leave every hub
     * showing an empty table until each row was edited by hand. Manila is the
     * head-office hub and where this stock was in practice.
     *
     * Only NULLs are touched, so a row someone has already assigned is left
     * alone and re-running this cannot move stock.
     */
    public function up(): void
    {
        $manila = DB::connection('db_inv_ho')->table('hub')
            ->whereRaw('LOWER(hub_name) = ?', ['manila'])
            ->value('id');

        if (! $manila) {
            return;
        }

        DB::connection('db_inv_ho')->table('receiving')
            ->whereNull('hub_id')
            ->update(['hub_id' => $manila]);
    }

    public function down(): void
    {
        // Deliberately not reversed. Which rows were NULL before is not
        // recorded anywhere, so blanking every Manila row on rollback would
        // also wipe hubs that were set on purpose after this ran.
    }
};
