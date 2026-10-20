<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('idempotency_key', 100);
            $t->string('request_hash', 64);
            $t->unsignedBigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('status', 20)->default('pending');
            $t->string('provider_reference')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();
            $t->string('provider_event_id')->unique();
            $t->uuid('payment_intent_id');
            $t->string('type', 50);
            $t->json('payload');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_intents');
    }
};
