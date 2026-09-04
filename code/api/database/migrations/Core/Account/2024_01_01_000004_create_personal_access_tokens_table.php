<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sanctum + colunas ip_address/user_agent (App\Models\Core\Account\PersonalAccessToken).
        // tokenable é ULID (User) → ulidMorphs.
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->ulidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
