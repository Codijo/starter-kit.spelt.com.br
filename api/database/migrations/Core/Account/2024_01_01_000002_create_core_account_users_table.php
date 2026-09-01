<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_account_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->nullable()->constrained('core_accounts')->nullOnDelete();
            $table->string('spelt_user_id', 26)->nullable()->index(); // ponte com o User do Spelt
            $table->string('name');
            $table->string('email');
            $table->string('avatar_url')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();                  // SSO-only: não gerido
            $table->timestamp('last_login_at')->nullable();
            $table->json('metadata')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['account_id', 'email']);                 // e-mail único por conta
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('core_account_users');
    }
};
