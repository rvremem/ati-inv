<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * The seven branches, seeded so the Hub dropdown is populated on every
     * environment rather than only where someone typed them in by hand.
     *
     * Seeded rather than hard-coded as an enum, for the same reason the
     * suppliers are: the rows can be renamed or added to later without a code
     * change, and machines/receiving reference them by id.
     */
    public const HUBS = [
        'Manila',
        'Cebu',
        'Davao',
        'Iloilo',
        'Baguio',
        'CDO',
        'Bicol',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::HUBS as $name) {
            // Idempotent: skip anything already on file so re-running this on a
            // database seeded by hand cannot duplicate a hub.
            $exists = DB::connection('db_inv_ho')->table('hub')
                ->whereRaw('LOWER(hub_name) = ?', [strtolower($name)])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::connection('db_inv_ho')->table('hub')->insert([
                'hub_name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Only removes a hub if nothing references it, so a rollback cannot
        // orphan users, machines or receiving rows booked against it.
        foreach (self::HUBS as $name) {
            $id = DB::connection('db_inv_ho')->table('hub')
                ->whereRaw('LOWER(hub_name) = ?', [strtolower($name)])
                ->value('id');

            if (! $id) {
                continue;
            }

            foreach (['users', 'machines', 'receiving'] as $table) {
                if (DB::connection('db_inv_ho')->table($table)->where('hub_id', $id)->exists()) {
                    continue 2;
                }
            }

            DB::connection('db_inv_ho')->table('hub')->where('id', $id)->delete();
        }
    }
};
