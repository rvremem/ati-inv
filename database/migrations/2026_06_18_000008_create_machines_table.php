<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        Schema::connection('db_inv_ho')->create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no')->unique();
            $table->string('description');
            $table->string('model')->nullable();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('ati_po')->nullable();
            $table->date('date_received');
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->string('status')->default('Received');
            $table->dateTime('out_date')->nullable();
            $table->dateTime('return_date')->nullable();
            $table->string('return_remarks')->nullable();
            $table->string('returned_by')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->dropIfExists('machines');
    }
};
