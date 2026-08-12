<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        Schema::connection('db_inv_ho')->create('defective_items', function (Blueprint $table) {
            $table->id();
            $table->date('date_received');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('engineer_id')->constrained('users');
            $table->foreignId('item_part_id')->constrained('item_parts');
            $table->foreignId('location_id')->constrained('locations');
            $table->integer('quantity')->default(1);
            $table->string('serial_no')->nullable();
            $table->string('return_remarks')->nullable();
            $table->dateTime('return_date')->nullable();
            $table->string('returned_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->dropIfExists('defective_items');
    }
};
