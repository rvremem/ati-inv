<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Where an action was performed from.
     *
     * A column rather than more text on the end of remarks: it can be searched,
     * sorted and exported on its own, and remarks is already carrying the
     * "who was tried" detail for failed sign-ins.
     *
     * 45 characters because that is the longest an IPv6 address gets, including
     * an IPv4-mapped tail like ::ffff:192.168.1.1.
     *
     * Failed sign-ins have recorded the IP inside remarks since logins were
     * first logged, so those are lifted into the new column here — the data
     * already exists and would otherwise stay unsearchable. The remarks text
     * is left exactly as written: rewriting audit records to tidy them is not
     * worth the precedent, so old rows keep their trailing "| IP: x" and the
     * report simply reads the column.
     */
    public function up(): void
    {
        Schema::connection('db_inv_ho')->table('activity_logs', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('remarks');
        });

        DB::connection('db_inv_ho')->statement("
            UPDATE activity_logs
            SET ip_address = LTRIM(RTRIM(
                SUBSTRING(remarks, CHARINDEX('| IP: ', remarks) + 6, 45)
            ))
            WHERE remarks LIKE '%| IP: %'
        ");
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->table('activity_logs', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};
