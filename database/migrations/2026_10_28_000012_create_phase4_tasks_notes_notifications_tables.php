<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->string('code', 30);
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('priority', 20)->default('medium'); // low, medium, high, critical
                $table->string('status', 30)->default('todo'); // todo, in_progress, review, completed, blocked
                $table->date('due_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_worker_id')->nullable()->constrained('workers')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['company_id', 'code']);
                $table->index(['company_id', 'site_id', 'status'], 'tasks_comp_site_status_idx');
                $table->index(['company_id', 'project_id', 'status'], 'tasks_comp_proj_status_idx');
                $table->index(['company_id', 'assigned_to', 'status'], 'tasks_comp_assignee_status_idx');
            });
        }

        if (!Schema::hasTable('task_comments')) {
            Schema::create('task_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('comment');
                $table->timestamps();

                $table->index(['company_id', 'task_id']);
            });
        }

        if (!Schema::hasTable('site_notes')) {
            Schema::create('site_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('category', 40)->default('general'); // general, safety_hse, quality, client_instruction, handover
                $table->string('title', 160);
                $table->text('body');
                $table->boolean('is_pinned')->default(false);
                $table->timestamps();

                $table->index(['company_id', 'site_id', 'is_pinned']);
                $table->index(['company_id', 'project_id']);
            });
        }

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 80);
                $table->string('subject_type', 120)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('description')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'site_id', 'created_at'], 'act_log_comp_site_time_idx');
                $table->index(['company_id', 'subject_type', 'subject_id'], 'act_log_subject_idx');
            });
        }

        if (!Schema::hasTable('crm_notifications')) {
            Schema::create('crm_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
                $table->string('type', 50)->default('general'); // task_assigned, task_status, correction_reviewed, attendance_flag, milestone_overdue, general
                $table->string('title', 180);
                $table->text('message');
                $table->string('action_url', 255)->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'user_id', 'read_at'], 'crm_notif_user_read_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('site_notes');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('tasks');
    }
};

