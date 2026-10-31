<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('worker_sequences')) {
            Schema::create('worker_sequences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('prefix', 16);
                $table->unsignedSmallInteger('year');
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'prefix', 'year'], 'worker_seq_comp_prefix_year_unique');
            });
        }

        Schema::table('workers', function (Blueprint $table) {
            if (!Schema::hasColumn('workers', 'skill_level')) {
                $table->string('skill_level', 40)->nullable()->after('department');
            }
            if (!Schema::hasColumn('workers', 'overtime_rate')) {
                $table->decimal('overtime_rate', 10, 2)->nullable()->default(0.00)->after('daily_rate');
            }
            if (!Schema::hasColumn('workers', 'bank_account_name')) {
                $table->string('bank_account_name', 120)->nullable()->after('emergency_contact_phone');
            }
            if (!Schema::hasColumn('workers', 'bank_account_number')) {
                $table->string('bank_account_number', 50)->nullable()->after('bank_account_name');
            }
            if (!Schema::hasColumn('workers', 'bank_ifsc')) {
                $table->string('bank_ifsc', 20)->nullable()->after('bank_account_number');
            }
            if (!Schema::hasColumn('workers', 'upi_id')) {
                $table->string('upi_id', 100)->nullable()->after('bank_ifsc');
            }
            if (!Schema::hasColumn('workers', 'exit_date')) {
                $table->date('exit_date')->nullable()->after('joining_date');
            }
        });

        if (Schema::hasTable('worker_documents')) {
            Schema::table('worker_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('worker_documents', 'document_number')) {
                    $table->string('document_number', 80)->nullable()->after('title');
                }
                if (!Schema::hasColumn('worker_documents', 'issue_date')) {
                    $table->date('issue_date')->nullable()->after('file_size');
                }
                if (!Schema::hasColumn('worker_documents', 'expiry_date')) {
                    $table->date('expiry_date')->nullable()->after('issue_date');
                    $table->index(['company_id', 'expiry_date'], 'worker_docs_company_expiry_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('worker_documents')) {
            Schema::table('worker_documents', function (Blueprint $table) {
                if (Schema::hasColumn('worker_documents', 'expiry_date')) {
                    $table->dropIndex('worker_docs_company_expiry_idx');
                    $table->dropColumn('expiry_date');
                }
                if (Schema::hasColumn('worker_documents', 'issue_date')) {
                    $table->dropColumn('issue_date');
                }
                if (Schema::hasColumn('worker_documents', 'document_number')) {
                    $table->dropColumn('document_number');
                }
            });
        }

        Schema::table('workers', function (Blueprint $table) {
            $dropCols = [];
            foreach ([
                'skill_level',
                'overtime_rate',
                'bank_account_name',
                'bank_account_number',
                'bank_ifsc',
                'upi_id',
                'exit_date',
            ] as $col) {
                if (Schema::hasColumn('workers', $col)) {
                    $dropCols[] = $col;
                }
            }
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });

        Schema::dropIfExists('worker_sequences');
    }
};

