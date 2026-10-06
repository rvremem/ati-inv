<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'db_inv_ho';

    /**
     * A password change that has been typed but not yet proved by email.
     *
     * The new password is parked here rather than written straight onto the
     * user, because the reset form is unauthenticated: anyone who knows a
     * username can submit it. Applying it immediately would let a stranger
     * lock a colleague out of their account without ever reading their mail.
     * Nothing moves to users.password until the emailed link is clicked.
     *
     * Already hashed on the way in — a pending password is still a password,
     * and this table is no safer than the users table.
     */
    public function up(): void
    {
        Schema::connection('db_inv_ho')->create('password_reset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('db_inv_ho')->dropIfExists('password_reset_requests');
    }
};
