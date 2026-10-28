<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('order_type', 30)->default('client_issued'); // client_issued, internal
            $table->string('order_number', 50);
            $table->string('client_reference', 100)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('scope_of_work')->nullable();
            $table->decimal('estimated_amount', 14, 2)->default(0.00);
            $table->date('target_start_date')->nullable();
            $table->date('target_completion_date')->nullable();
            
            // Workflow Status: draft -> submitted -> site_manager_approved -> senior_manager_approved -> in_progress -> completed (or rejected)
            $table->string('status', 40)->default('draft');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Tier 1: Site Manager Approval
            $table->foreignId('site_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('site_manager_approved_at')->nullable();
            $table->text('site_manager_notes')->nullable();

            // Tier 2: Senior Manager Approval
            $table->foreignId('senior_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('senior_manager_approved_at')->nullable();
            $table->text('senior_manager_notes')->nullable();

            // Rejection info
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'order_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'order_type']);
        });

        Schema::create('work_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('stage', 30); // site_manager, senior_manager
            $table->string('action', 20); // approved, rejected
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('work_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 100)->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_attachments');
        Schema::dropIfExists('work_order_approvals');
        Schema::dropIfExists('work_orders');
    }
};
