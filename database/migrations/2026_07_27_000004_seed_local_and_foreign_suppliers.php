<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * Supplier is a two-value classification, not a directory of vendors.
     *
     * The legacy ati_inv database has exactly these two rows in its `supplier`
     * table, and `receiving.supplier` has never held anything else across
     * 10,647 rows — 4,801 Foreign, 164 Local, the rest blank. ati-inv's
     * `suppliers` table was empty, so the Supplier dropdown on Receive Parts
     * rendered with no options at all.
     *
     * Seeded rather than hard-coded as an enum so the existing Suppliers CRUD
     * keeps working and the two rows can be renamed without a code change.
     */
    public const SUPPLIERS = [
        'Local Supplier',
        'Foreign Supplier',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::SUPPLIERS as $name) {
            // Idempotent: skip anything already on file so re-running the
            // migration on a database that was seeded by hand cannot duplicate.
            $exists = DB::connection('db_inv_ho')->table('suppliers')
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::connection('db_inv_ho')->table('suppliers')->insert([
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Only removes them if nothing references them, so a rollback cannot
        // orphan receiving rows that were booked against a supplier.
        foreach (self::SUPPLIERS as $name) {
            $id = DB::connection('db_inv_ho')->table('suppliers')
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->value('id');

            if ($id && ! DB::connection('db_inv_ho')->table('receiving')->where('supplier_id', $id)->exists()) {
                DB::connection('db_inv_ho')->table('suppliers')->where('id', $id)->delete();
            }
        }
    }
};
