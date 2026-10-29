<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A contract's customer is now the name as written, not a link to the
     * Customers table. Contracts arrive in bulk from the account managers'
     * spreadsheets, and requiring every client to be set up as a customer
     * first made the batch upload skip most of a file.
     *
     * Existing links are copied across as their name before the column goes,
     * so no contract loses its customer.
     */
    public function up(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->string('customer')->nullable()->after('equipment_type');
        });

        DB::statement('
            UPDATE mc SET mc.customer = c.name
            FROM maintenance_contracts mc
            JOIN customers c ON c.id = mc.customer_id
        ');

        // Not NULL once filled: the form and the upload both require it.
        DB::table('maintenance_contracts')->whereNull('customer')->update(['customer' => '']);

        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->string('customer')->nullable(false)->change();
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }

    /**
     * Restores the link column, nullable — a typed name may match no customer,
     * so the old NOT NULL foreign key cannot be put back as it was.
     */
    public function down(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('equipment_type')->constrained('customers');
        });

        DB::statement('
            UPDATE mc SET mc.customer_id = c.id
            FROM maintenance_contracts mc
            JOIN customers c ON c.name = mc.customer
        ');

        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->dropColumn('customer');
        });
    }
};
