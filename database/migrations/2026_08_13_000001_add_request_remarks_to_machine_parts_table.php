<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * What the requester typed when asking for a part to be pulled.
     *
     * The machine-part request form has always collected per-item remarks —
     * machinePartRequestStore() even validates `item_remarks` — but nothing
     * saved them, so they were discarded the moment the request was submitted.
     *
     * They cannot go in the existing `remarks` column: that one describes why
     * the part was *fitted* to the machine, which is a different question from
     * why it is being taken off, and overwriting it would lose that.
     *
     * Nullable because requests raised before this column existed have nothing
     * to recover, and the field is optional on the form anyway.
     */
    public function up(): void
    {
        if (! Schema::connection('db_inv_ho')->hasColumn('machine_parts', 'request_remarks')) {
            Schema::connection('db_inv_ho')->table('machine_parts', function (Blueprint $table) {
                // No ->after(): sqlsrv ignores column positioning.
                $table->string('request_remarks')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('db_inv_ho')->hasColumn('machine_parts', 'request_remarks')) {
            Schema::connection('db_inv_ho')->table('machine_parts', function (Blueprint $table) {
                $table->dropColumn('request_remarks');
            });
        }
    }
};
