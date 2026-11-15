<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workers')) {
            Schema::table('workers', function (Blueprint $table) {
                if (! Schema::hasColumn('workers', 'pf_status')) {
                    $table->string('pf_status', 20)->default('unconfigured')->after('upi_id');
                }
                if (! Schema::hasColumn('workers', 'pf_employee_rate')) {
                    $table->decimal('pf_employee_rate', 5, 2)->nullable()->after('pf_status');
                }
                if (! Schema::hasColumn('workers', 'pf_employer_rate')) {
                    $table->decimal('pf_employer_rate', 5, 2)->nullable()->after('pf_employee_rate');
                }
                if (! Schema::hasColumn('workers', 'pf_wage_ceiling')) {
                    $table->decimal('pf_wage_ceiling', 12, 2)->nullable()->after('pf_employer_rate');
                }
                if (! Schema::hasColumn('workers', 'uan_number')) {
                    $table->string('uan_number', 30)->nullable()->after('pf_wage_ceiling');
                }

                if (! Schema::hasColumn('workers', 'esic_status')) {
                    $table->string('esic_status', 20)->default('unconfigured')->after('uan_number');
                }
                if (! Schema::hasColumn('workers', 'esic_employee_rate')) {
                    $table->decimal('esic_employee_rate', 5, 2)->nullable()->after('esic_status');
                }
                if (! Schema::hasColumn('workers', 'esic_employer_rate')) {
                    $table->decimal('esic_employer_rate', 5, 2)->nullable()->after('esic_employee_rate');
                }
                if (! Schema::hasColumn('workers', 'esic_wage_ceiling')) {
                    $table->decimal('esic_wage_ceiling', 12, 2)->nullable()->after('esic_employer_rate');
                }
                if (! Schema::hasColumn('workers', 'esic_ip_number')) {
                    $table->string('esic_ip_number', 30)->nullable()->after('esic_wage_ceiling');
                }

                if (! Schema::hasColumn('workers', 'pt_status')) {
                    $table->string('pt_status', 20)->default('unconfigured')->after('esic_ip_number');
                }
                if (! Schema::hasColumn('workers', 'pt_monthly_amount')) {
                    $table->decimal('pt_monthly_amount', 10, 2)->nullable()->after('pt_status');
                }
                if (! Schema::hasColumn('workers', 'statutory_effective_from')) {
                    $table->date('statutory_effective_from')->nullable()->after('pt_monthly_amount');
                }
                if (! Schema::hasColumn('workers', 'statutory_updated_by')) {
                    $table->foreignId('statutory_updated_by')->nullable()->after('statutory_effective_from')->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('payroll_entries')) {
            Schema::table('payroll_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_entries', 'pf_employee_amount')) {
                    $table->decimal('pf_employee_amount', 12, 2)->default(0)->after('other_deduction');
                }
                if (! Schema::hasColumn('payroll_entries', 'pf_employer_amount')) {
                    $table->decimal('pf_employer_amount', 12, 2)->default(0)->after('pf_employee_amount');
                }
                if (! Schema::hasColumn('payroll_entries', 'esic_employee_amount')) {
                    $table->decimal('esic_employee_amount', 12, 2)->default(0)->after('pf_employer_amount');
                }
                if (! Schema::hasColumn('payroll_entries', 'esic_employer_amount')) {
                    $table->decimal('esic_employer_amount', 12, 2)->default(0)->after('esic_employee_amount');
                }
                if (! Schema::hasColumn('payroll_entries', 'pt_amount')) {
                    $table->decimal('pt_amount', 12, 2)->default(0)->after('esic_employer_amount');
                }
                if (! Schema::hasColumn('payroll_entries', 'statutory_config_state')) {
                    $table->string('statutory_config_state', 20)->default('unconfigured')->after('pt_amount');
                }
                if (! Schema::hasColumn('payroll_entries', 'statutory_snapshot')) {
                    $table->json('statutory_snapshot')->nullable()->after('statutory_config_state');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_entries')) {
            $cols = [
                'statutory_snapshot',
                'statutory_config_state',
                'pt_amount',
                'esic_employer_amount',
                'esic_employee_amount',
                'pf_employer_amount',
                'pf_employee_amount',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('payroll_entries', $col)) {
                    Schema::table('payroll_entries', function (Blueprint $table) use ($col) {
                        $table->dropColumn($col);
                    });
                }
            }
        }

        if (Schema::hasTable('workers')) {
            if (Schema::hasColumn('workers', 'statutory_updated_by')) {
                Schema::table('workers', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('statutory_updated_by');
                });
            }

            $cols = [
                'statutory_effective_from',
                'pt_monthly_amount',
                'pt_status',
                'esic_ip_number',
                'esic_wage_ceiling',
                'esic_employer_rate',
                'esic_employee_rate',
                'esic_status',
                'uan_number',
                'pf_wage_ceiling',
                'pf_employer_rate',
                'pf_employee_rate',
                'pf_status',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('workers', $col)) {
                    Schema::table('workers', function (Blueprint $table) use ($col) {
                        $table->dropColumn($col);
                    });
                }
            }
        }
    }
};
