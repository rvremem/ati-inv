<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * The branch a storage location physically sits in.
     *
     * A shelf exists at one site, so receiving into a hub should only offer
     * that hub's locations. Existing rows are backfilled to Manila, matching
     * what was done for receiving and machines.
     *
     * Consequence worth knowing: every location currently belongs to Manila,
     * so other hubs have none until they are created. Receiving into them will
     * show an empty location list until then.
     *
     * Nullable rather than NOT NULL: the column has to be added before it can
     * be filled, and the forms require it from here on, so a blank one can only
     * arrive from a direct database edit.
     */
    public function up(): void
    {
        Schema::connection('db_inv_ho')->table('locations', function (Blueprint $table) {
            $table->foreignId('hub_id')->nullable()->after('description')
                ->constrained('hub')->nullOnDelete();
        });

        $manila = DB::connection('db_inv_ho')->table('hub')
            ->whereRaw('LOWER(hub_name) = ?', ['manila'])
            ->value('id');

        if ($manila) {
            DB::connection('db_inv_ho')->table('locations')
                ->whereNull('hub_id')
                ->update(['hub_id' => $manila]);
        }
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->table('locations', function (Blueprint $table) {
            $table->dropForeign(['hub_id']);
            $table->dropColumn('hub_id');
        });
    }
};
