<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Roles were a free-text column on users, so nothing stopped a typo silently
     * failing every access gate. This makes them a real table.
     *
     * `code` is the stable identifier the application compares against and must
     * not change; `name` is the display label and is safe to reword.
     */
    public const ROLES = [
        ['code' => 'SA',          'name' => 'Super Admin',  'description' => 'Full access to every module.'],
        ['code' => 'Admin',       'name' => 'Admin',        'description' => 'Full access to day-to-day operations.'],
        ['code' => 'Developer',   'name' => 'Developer',    'description' => 'Top-of-chain access, including anything added later.'],
        ['code' => 'Engineer',    'name' => 'Engineer',     'description' => 'Raises requests and views their own history.'],
        ['code' => 'ServiceDesk', 'name' => 'Service Desk', 'description' => 'Manages maintenance contracts. Formerly "Maintenance".'],
        ['code' => 'PartsCenter', 'name' => 'Parts Center', 'description' => 'Reporting access.'],
    ];

    public function up(): void
    {
        Schema::connection('db_inv_ho')->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::connection('db_inv_ho')->table('roles')->insert(
            array_map(fn ($role) => $role + [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], self::ROLES)
        );
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->dropIfExists('roles');
    }
};
