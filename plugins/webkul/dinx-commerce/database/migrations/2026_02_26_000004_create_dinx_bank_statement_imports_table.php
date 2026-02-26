<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dinx_bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('uploaded')->index();
            $table->date('statement_start_date')->nullable();
            $table->date('statement_end_date')->nullable();
            $table->unsignedInteger('total_lines')->default(0);
            $table->unsignedInteger('matched_lines')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dinx_bank_statement_imports');
    }
};
