<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('title', 150)->nullable();
            $table->string('original_name', 255);
            $table->string('storage_disk', 30)->default('local');
            $table->string('file_path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'worker_id']);
            $table->index(['worker_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_documents');
    }
};

