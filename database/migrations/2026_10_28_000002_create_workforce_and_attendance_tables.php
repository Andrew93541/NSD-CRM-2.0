<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('gst_number', 30)->nullable();
            $table->string('pan_number', 30)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('worker_type', 30)->default('employee'); // employee, direct_labour, subcontractor_worker
            $table->foreignId('subcontractor_id')->nullable()->constrained('subcontractors')->nullOnDelete();
            $table->string('code', 30);
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('aadhaar_last_four', 4)->nullable();
            $table->string('trade_designation')->nullable(); // Mason, Operator, Helper, Supervisor, Welder, Electrician
            $table->foreignId('assigned_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->decimal('daily_rate', 10, 2)->default(0.00);
            $table->string('status', 20)->default('active'); // active, inactive, terminated
            $table->date('joining_date')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'worker_type']);
            $table->index(['company_id', 'assigned_site_id']);
        });

        Schema::create('daily_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('status', 20)->default('present'); // present, half_day, absent, paid_leave
            $table->decimal('worked_hours', 5, 2)->default(0.00);
            $table->decimal('overtime_hours', 5, 2)->default(0.00);
            $table->boolean('has_conflict')->default(false);
            $table->text('conflict_reason')->nullable();
            $table->string('approval_status', 30)->default('pending_approval'); // draft, pending_approval, approved, rejected
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // Crucial: non-unique index to support multiple legitimate work periods and multi-site records on same date
            $table->index(['company_id', 'worker_id', 'attendance_date'], 'att_comp_worker_date_idx');
            $table->index(['company_id', 'site_id', 'attendance_date'], 'att_comp_site_date_idx');
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('daily_attendance_id')->constrained('daily_attendances')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('previous_status', 20);
            $table->string('proposed_status', 20);
            $table->time('previous_clock_in')->nullable();
            $table->time('proposed_clock_in')->nullable();
            $table->time('previous_clock_out')->nullable();
            $table->time('proposed_clock_out')->nullable();
            $table->foreignId('previous_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('proposed_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->decimal('previous_worked_hours', 5, 2)->default(0.00);
            $table->decimal('proposed_worked_hours', 5, 2)->default(0.00);
            $table->decimal('previous_overtime_hours', 5, 2)->default(0.00);
            $table->decimal('proposed_overtime_hours', 5, 2)->default(0.00);

            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('daily_attendances');
        Schema::dropIfExists('workers');
        Schema::dropIfExists('subcontractors');
    }
};
