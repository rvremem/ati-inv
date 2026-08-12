<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Replaces the free-text users.role column with a foreign key to roles.
     *
     * The old 'Maintenance' role is carried over to 'ServiceDesk' — same role,
     * new label — so existing Maintenance users keep their access.
     */
    private const CODE_MAP = [
        'SA'          => 'SA',
        'Admin'       => 'Admin',
        'Developer'   => 'Developer',
        'Engineer'    => 'Engineer',
        'Maintenance' => 'ServiceDesk',
        'ServiceDesk' => 'ServiceDesk',
        'PartsCenter' => 'PartsCenter',
    ];

    public function up(): void
    {
        $connection = Schema::connection('db_inv_ho');

        if (!$connection->hasColumn('users', 'role_id')) {
            $connection->table('users', function (Blueprint $table) {
                // Nullable so the column can be added before it is backfilled;
                // any user whose old role didn't map is left null rather than
                // being silently handed someone else's access.
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            });
        }

        if ($connection->hasColumn('users', 'role')) {
            $roleIds = DB::connection('db_inv_ho')->table('roles')->pluck('id', 'code');

            foreach (self::CODE_MAP as $oldRole => $newCode) {
                if (!isset($roleIds[$newCode])) {
                    continue;
                }

                DB::connection('db_inv_ho')->table('users')
                    ->where('role', $oldRole)
                    ->update(['role_id' => $roleIds[$newCode]]);
            }

            $connection->table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        $connection = Schema::connection('db_inv_ho');

        if (!$connection->hasColumn('users', 'role')) {
            $connection->table('users', function (Blueprint $table) {
                $table->string('role')->nullable();
            });

            // Reverse the mapping — ServiceDesk goes back to Maintenance.
            $reverse = ['ServiceDesk' => 'Maintenance'];
            foreach (DB::connection('db_inv_ho')->table('roles')->get() as $role) {
                DB::connection('db_inv_ho')->table('users')
                    ->where('role_id', $role->id)
                    ->update(['role' => $reverse[$role->code] ?? $role->code]);
            }
        }

        if ($connection->hasColumn('users', 'role_id')) {
            $connection->table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            });
        }
    }
};
