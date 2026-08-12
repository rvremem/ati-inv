<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        Schema::connection('db_inv_ho')->create('customer_item_parts', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('item_part_id')->constrained('item_parts')->cascadeOnDelete();
            $table->primary(['customer_id', 'item_part_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->dropIfExists('customer_item_parts');
    }
};
