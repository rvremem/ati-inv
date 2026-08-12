<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        Schema::connection('db_inv_ho')->create('receiving', function (Blueprint $table) {
            $table->id();
            $table->date('date_received');
            $table->string('ati_po')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('item_part_id')->constrained('item_parts');
            $table->foreignId('location_id')->constrained('locations');
            $table->integer('quantity')->default(1);
            $table->string('serial_no')->nullable();
            $table->string('status')->default('Received');
            $table->string('return_remarks')->nullable();
            $table->dateTime('return_date')->nullable();
            $table->string('returned_by')->nullable();
            $table->dateTime('location_updated_at')->nullable();
            $table->string('location_updated_by')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->dropIfExists('receiving');
    }
};
