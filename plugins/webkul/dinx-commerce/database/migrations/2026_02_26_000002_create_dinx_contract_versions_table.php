<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_contract_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('dinx_contracts')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('label')->nullable();
            $table->string('status')->nullable();
            $table->longText('terms_html')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'version_number']);
            $table->index(['contract_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_contract_versions');
    }
};
