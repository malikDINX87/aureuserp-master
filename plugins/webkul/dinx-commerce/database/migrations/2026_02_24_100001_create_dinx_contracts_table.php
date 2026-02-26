<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners_partners')->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('draft')->index();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('amount_total', 20, 4)->default(0);
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->longText('terms_html')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('docusign_envelope_id')->nullable()->index();
            $table->string('signed_document_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_contracts');
    }
};
