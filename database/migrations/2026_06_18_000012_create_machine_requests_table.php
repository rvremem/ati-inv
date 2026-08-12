<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    public function up(): void
    {
        Schema::connection('db_inv_ho')->create('machine_requests', function (Blueprint $table) {
            $table->id();
            $table->date('pick_date');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('machine_id')->constrained('machines');
            $table->string('description')->nullable();
            $table->string('remarks')->nullable();
            $table->string('status')->default('Pending');
            $table->string('purpose')->nullable();
            $table->string('mris_no')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('approved_by')->nullable();
            $table->dateTime('out_date')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->dropIfExists('machine_requests');
    }
};
