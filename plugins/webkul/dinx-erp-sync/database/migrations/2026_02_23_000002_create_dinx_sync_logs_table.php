<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_id')->unique();
            $table->string('event');
            $table->string('status')->default('received')->index();
            $table->string('external_lead_id')->nullable()->index();
            $table->foreignId('partner_id')->nullable()->constrained('partners_partners')->nullOnDelete();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_sync_logs');
    }
};
