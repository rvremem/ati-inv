<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        Schema::connection('db_inv_ho')->create('item_parts', function (Blueprint $table) {
            $table->id();
            $table->string('part_no');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('description');
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->integer('min_stock')->default(0);
            $table->boolean('is_serialized')->default(false);
            $table->string('type');
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->dropIfExists('item_parts');
    }
};
