<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            if (!Schema::hasColumn('workers', 'pay_type')) {
                $table->string('pay_type', 30)->default('daily')->after('assigned_site_id'); // daily, monthly, hourly, contract
            }
            if (!Schema::hasColumn('workers', 'monthly_salary')) {
                $table->decimal('monthly_salary', 12, 2)->nullable()->default(0.00)->after('daily_rate');
            }
            if (!Schema::hasColumn('workers', 'hourly_rate')) {
                $table->decimal('hourly_rate', 10, 2)->nullable()->default(0.00)->after('monthly_salary');
            }
            if (!Schema::hasColumn('workers', 'contract_rate')) {
                $table->decimal('contract_rate', 12, 2)->nullable()->default(0.00)->after('hourly_rate');
            }
        });

        if (!Schema::hasTable('payroll_periods')) {
            Schema::create('payroll_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
                $table->string('period_code', 40);
                $table->string('title', 150);
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status', 30)->default('draft'); // draft, calculated, approved, locked, paid
                $table->unsignedInteger('total_workers')->default(0);
                $table->decimal('total_gross_amount', 14, 2)->default(0.00);
                $table->decimal('total_deductions', 14, 2)->default(0.00);
                $table->decimal('total_net_amount', 14, 2)->default(0.00);
                $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'period_code'], 'payroll_periods_comp_code_uniq');
                $table->index(['company_id', 'site_id', 'status'], 'payroll_periods_comp_site_status_idx');
            });
        }

        if (!Schema::hasTable('salary_advances')) {
            Schema::create('salary_advances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
                $table->date('advance_date');
                $table->decimal('amount', 12, 2);
                $table->decimal('deducted_amount', 12, 2)->default(0.00);
                $table->string('status', 30)->default('approved'); // pending, approved, deducted, rejected
                $table->string('reason', 255)->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('payroll_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'worker_id', 'status'], 'sal_adv_comp_worker_status_idx');
            });
        }

        if (!Schema::hasTable('payroll_entries')) {
            Schema::create('payroll_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
                $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
                $table->string('pay_type', 30)->default('daily'); // daily, monthly, hourly, contract
                $table->decimal('present_days', 5, 2)->default(0.00);
                $table->decimal('half_days', 5, 2)->default(0.00);
                $table->decimal('paid_leave_days', 5, 2)->default(0.00);
                $table->decimal('holiday_days', 5, 2)->default(0.00);
                $table->decimal('effective_days', 5, 2)->default(0.00);
                $table->decimal('worked_hours', 7, 2)->default(0.00);
                $table->decimal('overtime_hours', 7, 2)->default(0.00);
                $table->decimal('base_rate', 12, 2)->default(0.00);
                $table->decimal('overtime_rate', 10, 2)->default(0.00);
                $table->decimal('base_pay', 12, 2)->default(0.00);
                $table->decimal('overtime_pay', 12, 2)->default(0.00);
                $table->decimal('bonus_amount', 12, 2)->default(0.00);
                $table->decimal('gross_pay', 12, 2)->default(0.00);
                $table->decimal('advance_deduction', 12, 2)->default(0.00);
                $table->decimal('other_deduction', 12, 2)->default(0.00);
                $table->string('deduction_notes', 255)->nullable();
                $table->decimal('net_pay', 12, 2)->default(0.00);
                $table->string('payment_status', 30)->default('pending'); // pending, paid
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->unique(['payroll_period_id', 'worker_id'], 'payroll_entries_period_worker_uniq');
                $table->index(['company_id', 'worker_id'], 'payroll_entries_comp_worker_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('salary_advances');
        Schema::dropIfExists('payroll_periods');

        Schema::table('workers', function (Blueprint $table) {
            $dropCols = array_filter(
                ['pay_type', 'monthly_salary', 'hourly_rate', 'contract_rate'],
                fn ($c) => Schema::hasColumn('workers', $c)
            );
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};

