<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_sync_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('external_lead_id')->unique();
            $table->foreignId('partner_id')->constrained('partners_partners')->cascadeOnDelete();
            $table->string('last_delivery_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_sync_mappings');
    }
};
