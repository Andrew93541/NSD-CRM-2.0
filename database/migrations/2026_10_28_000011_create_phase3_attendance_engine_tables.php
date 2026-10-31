<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            if (!Schema::hasColumn('workers', 'pin')) {
                $table->string('pin')->nullable()->after('upi_id');
            }
            if (!Schema::hasColumn('workers', 'must_change_pin')) {
                $table->boolean('must_change_pin')->default(true)->after('pin');
            }
            if (!Schema::hasColumn('workers', 'qr_token')) {
                $table->string('qr_token', 64)->nullable()->unique()->after('must_change_pin');
            }
        });

        if (!Schema::hasTable('shifts')) {
            Schema::create('shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->cascadeOnDelete();
                $table->string('name', 100);
                $table->time('start_time')->default('09:00:00');
                $table->time('end_time')->default('18:00:00');
                $table->unsignedSmallInteger('grace_minutes')->default(15);
                $table->decimal('half_day_hours', 4, 2)->default(4.00);
                $table->decimal('full_day_hours', 4, 2)->default(8.00);
                $table->decimal('overtime_after_hours', 4, 2)->default(8.00);
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['company_id', 'site_id']);
            });
        }

        Schema::table('daily_attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_attendances', 'shift_id')) {
                $table->foreignId('shift_id')->nullable()->after('worker_id')->constrained('shifts')->nullOnDelete();
            }
            if (!Schema::hasColumn('daily_attendances', 'late_minutes')) {
                $table->unsignedInteger('late_minutes')->default(0)->after('overtime_hours');
            }
            if (!Schema::hasColumn('daily_attendances', 'lock_stage')) {
                $table->string('lock_stage', 30)->default('open')->after('approval_status');
            }
        });

        if (!Schema::hasTable('attendance_punches')) {
            Schema::create('attendance_punches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
                $table->foreignId('daily_attendance_id')->nullable()->constrained('daily_attendances')->nullOnDelete();
                $table->string('punch_type', 10)->default('in'); // in, out
                $table->timestamp('punched_at'); // Server timestamp only
                $table->string('source', 30)->default('supervisor'); // self, qr, supervisor, kiosk
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('accuracy_m', 8, 2)->nullable();
                $table->decimal('distance_m', 10, 2)->nullable();
                $table->boolean('within_geofence')->nullable();
                $table->string('device_id', 120)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('selfie_path')->nullable();
                $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'worker_id', 'punched_at'], 'att_punch_comp_worker_time_idx');
                $table->index(['company_id', 'site_id', 'punched_at'], 'att_punch_comp_site_time_idx');
            });
        }

        if (!Schema::hasTable('attendance_locks')) {
            Schema::create('attendance_locks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->date('attendance_date');
                $table->string('stage', 30)->default('open'); // open, submitted, site_locked, payroll_locked
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('site_locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('site_locked_at')->nullable();
                $table->foreignId('payroll_locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('payroll_locked_at')->nullable();
                $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('unlocked_at')->nullable();
                $table->text('unlock_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'site_id', 'attendance_date'], 'att_lock_comp_site_date_uniq');
            });
        }

        if (!Schema::hasTable('attendance_flags')) {
            Schema::create('attendance_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
                $table->foreignId('daily_attendance_id')->nullable()->constrained('daily_attendances')->cascadeOnDelete();
                $table->foreignId('attendance_punch_id')->nullable()->constrained('attendance_punches')->nullOnDelete();
                $table->date('attendance_date');
                $table->string('flag_type', 40); // outside_geofence, shared_device, identical_crew_times, impossible_travel, overlapping_periods
                $table->string('severity', 20)->default('warning'); // info, warning, critical
                $table->text('description');
                $table->string('status', 20)->default('open'); // open, resolved, dismissed
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'site_id', 'status'], 'att_flag_comp_site_status_idx');
                $table->index(['company_id', 'worker_id', 'attendance_date'], 'att_flag_comp_worker_date_idx');
            });
        }

        if (!Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->cascadeOnDelete();
                $table->string('name', 150);
                $table->date('holiday_date');
                $table->boolean('is_paid')->default(true);
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'holiday_date']);
            });
        }

        if (!Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('code', 30);
                $table->boolean('is_paid')->default(true);
                $table->unsignedSmallInteger('annual_quota')->default(12);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code']);
            });
        }

        if (!Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
                $table->foreignId('leave_type_id')->nullable()->constrained('leave_types')->nullOnDelete();
                $table->string('leave_type_name', 60)->default('Paid Leave');
                $table->boolean('is_paid')->default(true);
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedSmallInteger('days_count')->default(1);
                $table->text('reason');
                $table->string('status', 20)->default('pending'); // pending, approved, rejected
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'worker_id', 'start_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('attendance_flags');
        Schema::dropIfExists('attendance_locks');
        Schema::dropIfExists('attendance_punches');

        Schema::table('daily_attendances', function (Blueprint $table) {
            if (Schema::hasColumn('daily_attendances', 'shift_id')) {
                $table->dropConstrainedForeignId('shift_id');
            }
            $dropCols = array_filter(['late_minutes', 'lock_stage'], fn ($c) => Schema::hasColumn('daily_attendances', $c));
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });

        Schema::dropIfExists('shifts');

        Schema::table('workers', function (Blueprint $table) {
            if (Schema::hasColumn('workers', 'qr_token')) {
                $table->dropUnique(['qr_token']);
            }
            $dropCols = array_filter(['pin', 'must_change_pin', 'qr_token'], fn ($c) => Schema::hasColumn('workers', $c));
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};

