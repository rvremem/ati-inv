<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * The column Laravel's "remember me" writes to, which the users table never
     * had.
     *
     * LoginController calls Auth::login($user, true) — the second argument is
     * remember-me — so every successful sign-in tried to UPDATE users SET
     * remember_token and threw "Invalid column name 'remember_token'". The
     * controller's catch swallowed it into a generic "An error occurred"
     * message, which meant:
     *
     *   - ActivityLog::log('Logged In') on the line below never ran, so not one
     *     login had ever been recorded (0 rows, against 6 'Logged Out' rows);
     *   - users were bounced back to the login screen with an error even though
     *     the session was authenticated;
     *   - remember-me never worked at all.
     *
     * Adding the column is the standard fix and keeps the intended behaviour,
     * rather than dropping remember-me to work around a missing column.
     */
    public function up(): void
    {
        if (! Schema::connection('db_inv_ho')->hasColumn('users', 'remember_token')) {
            Schema::connection('db_inv_ho')->table('users', function (Blueprint $table) {
                $table->rememberToken();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('db_inv_ho')->hasColumn('users', 'remember_token')) {
            Schema::connection('db_inv_ho')->table('users', function (Blueprint $table) {
                $table->dropColumn('remember_token');
            });
        }
    }
};
