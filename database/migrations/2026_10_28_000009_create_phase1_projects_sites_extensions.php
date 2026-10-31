<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->unsignedInteger('geofence_radius_m')->default(200)->after('longitude');
            $table->time('shift_start')->nullable()->default('09:00:00')->after('geofence_radius_m');
            $table->time('shift_end')->nullable()->default('18:00:00')->after('shift_start');
            $table->foreignId('site_manager_id')->nullable()->after('supervisor_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('project_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'site_id']);
            $table->index(['company_id', 'site_id']);
        });

        Schema::create('site_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('workers')->cascadeOnDelete();
            $table->string('role_on_site', 50)->default('worker');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'site_id', 'is_active'], 'sa_comp_site_active_idx');
            $table->index(['company_id', 'user_id', 'is_active'], 'sa_comp_user_active_idx');
            $table->index(['company_id', 'worker_id', 'is_active'], 'sa_comp_worker_active_idx');
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('pending'); // pending, in_progress, completed, delayed
            $table->unsignedTinyInteger('percent_complete')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'status'], 'pm_comp_proj_status_idx');
            $table->index(['company_id', 'site_id'], 'pm_comp_site_idx');
        });

        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('document_type', 50)->default('drawing'); // drawing, permit, contract, boq, safety_plan, other
            $table->string('title', 200);
            $table->unsignedInteger('version')->default(1);
            $table->string('original_name', 255);
            $table->string('storage_disk', 30)->default('local');
            $table->string('file_path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->json('visibility_roles')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'project_id', 'document_type'], 'pd_comp_proj_type_idx');
            $table->index(['company_id', 'site_id'], 'pd_comp_site_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('site_assignments');
        Schema::dropIfExists('project_sites');

        Schema::table('sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_manager_id');
            $table->dropColumn([
                'geofence_radius_m',
                'shift_start',
                'shift_end',
            ]);
        });
    }
};

