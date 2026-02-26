<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_recurring_invoice_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('source_invoice_id')->nullable()->constrained('accounts_account_moves')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners_partners')->nullOnDelete();
            $table->string('interval')->default('monthly');
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('auto_send')->default(false);
            $table->boolean('allow_paypal')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->string('currency', 10)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_recurring_invoice_profiles');
    }
};
