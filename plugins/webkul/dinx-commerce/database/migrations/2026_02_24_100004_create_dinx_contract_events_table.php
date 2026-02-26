<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_contract_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->nullable()->constrained('dinx_contracts')->nullOnDelete();
            $table->string('provider');
            $table->string('event_type');
            $table->string('provider_event_id')->nullable()->index();
            $table->string('status')->nullable();
            $table->json('payload')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_contract_events');
    }
};
