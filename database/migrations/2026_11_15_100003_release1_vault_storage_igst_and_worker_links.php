<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['work_order_attachments', 'work_order_uploads', 'bill_exports', 'measurement_entries'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'storage_disk')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('storage_disk', 30)->default('local');
                });
            }
        }

        if (Schema::hasTable('companies') && ! Schema::hasColumn('companies', 'signature_disk')) {
            Schema::table('companies', function (Blueprint $table) {
                $col = $table->string('signature_disk', 30)->default('local');
                if (Schema::hasColumn('companies', 'signature_path')) {
                    $col->after('signature_path');
                }
            });
        }

        if (Schema::hasTable('workers')) {
            if (! Schema::hasColumn('workers', 'user_id')) {
                Schema::table('workers', function (Blueprint $table) {
                    $table->foreignId('user_id')
                        ->nullable()
                        ->after('company_id')
                        ->constrained('users')
                        ->nullOnDelete();
                });
            }

            if (! Schema::hasIndex('workers', ['company_id', 'user_id']) && ! Schema::hasIndex('workers', 'workers_company_user_unique')) {
                Schema::table('workers', function (Blueprint $table) {
                    $table->unique(['company_id', 'user_id'], 'workers_company_user_unique');
                });
            }
        }

        if (Schema::hasTable('work_orders')) {
            Schema::table('work_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('work_orders', 'tax_mode')) {
                    $table->string('tax_mode', 20)->default('cgst_sgst')->after('payment_terms');
                }
                if (! Schema::hasColumn('work_orders', 'igst_rate')) {
                    $table->decimal('igst_rate', 5, 2)->default(0.00)->after('sgst_rate');
                }
            });
        }

        if (Schema::hasTable('ra_bills')) {
            Schema::table('ra_bills', function (Blueprint $table) {
                if (! Schema::hasColumn('ra_bills', 'tax_mode')) {
                    $table->string('tax_mode', 20)->default('cgst_sgst')->after('excess_unbilled_amount');
                }
                if (! Schema::hasColumn('ra_bills', 'igst_rate')) {
                    $table->decimal('igst_rate', 5, 2)->default(0.00)->after('sgst_rate');
                }
                if (! Schema::hasColumn('ra_bills', 'igst_amount')) {
                    $table->decimal('igst_amount', 16, 2)->default(0.00)->after('sgst_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ra_bills')) {
            foreach (['igst_amount', 'igst_rate', 'tax_mode'] as $col) {
                if (Schema::hasColumn('ra_bills', $col)) {
                    Schema::table('ra_bills', function (Blueprint $table) use ($col) {
                        $table->dropColumn($col);
                    });
                }
            }
        }

        if (Schema::hasTable('work_orders')) {
            foreach (['igst_rate', 'tax_mode'] as $col) {
                if (Schema::hasColumn('work_orders', $col)) {
                    Schema::table('work_orders', function (Blueprint $table) use ($col) {
                        $table->dropColumn($col);
                    });
                }
            }
        }

        if (Schema::hasTable('workers')) {
            if (Schema::hasIndex('workers', 'workers_company_user_unique')) {
                Schema::table('workers', function (Blueprint $table) {
                    $table->dropUnique('workers_company_user_unique');
                });
            }
            if (Schema::hasColumn('workers', 'user_id')) {
                Schema::table('workers', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('user_id');
                });
            }
        }

        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'signature_disk')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('signature_disk');
            });
        }

        foreach (['work_order_attachments', 'work_order_uploads', 'bill_exports', 'measurement_entries'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'storage_disk')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('storage_disk');
                });
            }
        }
    }
};
