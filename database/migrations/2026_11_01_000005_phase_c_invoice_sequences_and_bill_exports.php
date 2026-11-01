<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_sequences')) {
            Schema::create('invoice_sequences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('prefix', 30)->default('CIVIL');
                $table->string('financial_year', 15); // e.g. 26-27
                $table->unsignedInteger('last_sequence')->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'prefix', 'financial_year'], 'invoice_sequences_company_prefix_fy_unique');
            });
        }

        if (! Schema::hasTable('bill_exports')) {
            Schema::create('bill_exports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
                $table->foreignId('ra_bill_id')->nullable()->constrained('ra_bills')->cascadeOnDelete();
                $table->string('export_type', 40); // tax_invoice_pdf, abstract_pdf, combined_pdf, ra_bill_xlsx, work_order_pdf
                $table->string('file_name');
                $table->string('file_path');
                $table->string('invoice_number', 80)->nullable();
                $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['work_order_id', 'ra_bill_id', 'export_type'], 'bill_exports_wo_bill_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_exports');
        Schema::dropIfExists('invoice_sequences');
    }
};

