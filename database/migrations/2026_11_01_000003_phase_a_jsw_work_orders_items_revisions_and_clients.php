<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'registered_address')) {
                $table->text('registered_address')->nullable();
            }
            if (!Schema::hasColumn('companies', 'gstin')) {
                $table->string('gstin', 30)->nullable();
            }
            if (!Schema::hasColumn('companies', 'pan')) {
                $table->string('pan', 20)->nullable();
            }
            if (!Schema::hasColumn('companies', 'vendor_code')) {
                $table->string('vendor_code', 50)->nullable();
            }
            if (!Schema::hasColumn('companies', 'bank_name')) {
                $table->string('bank_name', 120)->nullable();
            }
            if (!Schema::hasColumn('companies', 'bank_account_no')) {
                $table->string('bank_account_no', 50)->nullable();
            }
            if (!Schema::hasColumn('companies', 'bank_ifsc')) {
                $table->string('bank_ifsc', 25)->nullable();
            }
            if (!Schema::hasColumn('companies', 'bank_branch')) {
                $table->string('bank_branch', 120)->nullable();
            }
            if (!Schema::hasColumn('companies', 'signature_path')) {
                $table->string('signature_path', 255)->nullable();
            }
            if (!Schema::hasColumn('companies', 'invoice_prefix')) {
                $table->string('invoice_prefix', 30)->default('CIVIL');
            }
            if (!Schema::hasColumn('companies', 'invoice_starting_number')) {
                $table->unsignedInteger('invoice_starting_number')->default(21);
            }
            if (!Schema::hasColumn('companies', 'invoice_rounding_mode')) {
                $table->string('invoice_rounding_mode', 20)->default('paisa'); // paisa, rupee
            }
            if (!Schema::hasColumn('companies', 'work_order_terms_template')) {
                $table->text('work_order_terms_template')->nullable();
            }
        });

        if (!Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('code', 40)->nullable();
                $table->string('name', 200);
                $table->text('address')->nullable();
                $table->string('gstin', 30)->nullable();
                $table->string('pan', 20)->nullable();
                $table->string('vendor_code', 50)->nullable();
                $table->string('contact_person', 150)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email', 150)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'is_active'], 'clients_comp_active_idx');
            });
        }

        Schema::table('work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('work_orders', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('site_id')->constrained('clients')->nullOnDelete();
            }
            if (!Schema::hasColumn('work_orders', 'po_number')) {
                $table->string('po_number', 100)->nullable()->after('order_number');
            }
            if (!Schema::hasColumn('work_orders', 'po_date')) {
                $table->date('po_date')->nullable()->after('po_number');
            }
            if (!Schema::hasColumn('work_orders', 'job_location')) {
                $table->string('job_location', 255)->nullable()->after('po_date');
            }
            if (!Schema::hasColumn('work_orders', 'user_department_person')) {
                $table->string('user_department_person', 150)->nullable()->after('job_location');
            }
            if (!Schema::hasColumn('work_orders', 'effective_date')) {
                $table->date('effective_date')->nullable()->after('user_department_person');
            }
            if (!Schema::hasColumn('work_orders', 'completion_date')) {
                $table->date('completion_date')->nullable()->after('effective_date');
            }
            if (!Schema::hasColumn('work_orders', 'cgst_rate')) {
                $table->decimal('cgst_rate', 5, 2)->default(9.00)->after('estimated_amount');
            }
            if (!Schema::hasColumn('work_orders', 'sgst_rate')) {
                $table->decimal('sgst_rate', 5, 2)->default(9.00)->after('cgst_rate');
            }
            if (!Schema::hasColumn('work_orders', 'retention_percent')) {
                $table->decimal('retention_percent', 5, 2)->default(5.00)->after('sgst_rate');
            }
            if (!Schema::hasColumn('work_orders', 'current_revision')) {
                $table->unsignedInteger('current_revision')->default(0)->after('retention_percent');
            }
            if (!Schema::hasColumn('work_orders', 'base_order_value')) {
                $table->decimal('base_order_value', 16, 2)->default(0.00)->after('current_revision');
            }
        });

        if (!Schema::hasTable('work_order_items')) {
            Schema::create('work_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
                $table->string('sr_no', 30); // TEXT/string like '1.10' so trailing zero is preserved
                $table->string('service_code', 60)->nullable();
                $table->string('sac_code', 30)->nullable();
                $table->string('description', 500);
                $table->text('specification')->nullable();
                $table->string('unit', 30);
                $table->decimal('ordered_quantity', 14, 3)->default(0.000);
                $table->decimal('rate', 14, 2)->default(0.00);
                $table->decimal('line_amount', 16, 2)->default(0.00);
                $table->date('completion_date')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['company_id', 'work_order_id'], 'wo_items_comp_wo_idx');
            });
        }

        if (!Schema::hasTable('work_order_revisions')) {
            Schema::create('work_order_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
                $table->unsignedInteger('revision_number');
                $table->text('reason');
                $table->decimal('previous_base_value', 16, 2)->default(0.00);
                $table->decimal('new_base_value', 16, 2)->default(0.00);
                $table->json('items_snapshot')->nullable();
                $table->json('proposed_items')->nullable();
                $table->string('status', 30)->default('approved'); // pending_approval, approved, rejected
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->unique(['work_order_id', 'revision_number'], 'wo_revisions_wo_rev_uniq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_revisions');
        Schema::dropIfExists('work_order_items');

        Schema::table('work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('work_orders', 'client_id')) {
                $table->dropConstrainedForeignId('client_id');
            }
            $dropCols = array_filter([
                'po_number', 'po_date', 'job_location', 'user_department_person',
                'effective_date', 'completion_date', 'cgst_rate', 'sgst_rate',
                'retention_percent', 'current_revision', 'base_order_value',
            ], fn ($c) => Schema::hasColumn('work_orders', $c));
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });

        Schema::dropIfExists('clients');

        Schema::table('companies', function (Blueprint $table) {
            $dropCols = array_filter([
                'registered_address', 'gstin', 'pan', 'vendor_code', 'bank_name',
                'bank_account_no', 'bank_ifsc', 'bank_branch', 'signature_path',
                'invoice_prefix', 'invoice_starting_number', 'invoice_rounding_mode',
                'work_order_terms_template',
            ], fn ($c) => Schema::hasColumn('companies', $c));
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};

