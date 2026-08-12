<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        Schema::connection('db_inv_ho')->create('defective_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_part_id')->constrained();
            $table->string('serial_no')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('reason')->nullable();
            $table->foreignId('location_id')->constrained();
            $table->string('status')->default('Defective');
            $table->foreignId('created_by')->constrained('users');
            $table->text('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->dropIfExists('defective_parts');
    }
};
