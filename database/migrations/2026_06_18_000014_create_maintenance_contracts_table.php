<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        Schema::connection('db_inv_ho')->create('maintenance_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('account_manager');
            $table->string('equipment_type');
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('po_no');
            $table->string('sites');
            $table->string('brand');
            $table->string('description');
            $table->string('location');
            $table->string('company');
            $table->string('branch');
            $table->string('serial_no');
            $table->integer('coverage_months');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->dropIfExists('maintenance_contracts');
    }
};
