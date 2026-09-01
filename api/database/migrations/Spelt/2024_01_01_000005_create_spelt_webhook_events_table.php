<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotência + auditoria dos webhooks recebidos do Spelt.
        Schema::create('spelt_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('spelt_event_id')->unique();   // dedup
            $table->string('event');
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('status')->default('received'); // received | processed | failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spelt_webhook_events');
    }
};
