<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Email verification timestamp, the column Laravel's MustVerifyEmail needs.
     *
     * Existing users are backfilled as already verified. Login now refuses an
     * unverified account, so leaving them NULL would lock every current user
     * out of the system the moment this migration ran — they registered before
     * verification existed and never had a link to click.
     */
    public function up(): void
    {
        Schema::connection('db_inv_ho')->table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        DB::connection('db_inv_ho')->table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
