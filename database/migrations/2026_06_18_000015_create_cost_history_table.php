<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        Schema::connection('db_inv_ho')->create('cost_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_part_id')->constrained('item_parts')->cascadeOnDelete();
            $table->decimal('previous_cost', 18, 2);
            $table->decimal('new_cost', 18, 2);
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->dropIfExists('cost_history');
    }
};
