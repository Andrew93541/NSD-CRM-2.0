<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('work_order_uploads')) {
            Schema::create('work_order_uploads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
                $table->string('original_filename');
                $table->string('file_path');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('mime_type', 120)->default('application/pdf');
                $table->string('status', 40)->default('uploaded')->index();
                $table->boolean('is_scanned_or_unreadable')->default(false);
                $table->boolean('is_draft_po')->default(false);
                $table->json('extracted_payload')->nullable();
                $table->json('reviewed_payload')->nullable();
                $table->json('validation_errors')->nullable();
                $table->text('override_reason')->nullable();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
            });
        }

        if (Schema::hasTable('work_orders')) {
            Schema::table('work_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('work_orders', 'lifecycle_status')) {
                    $table->string('lifecycle_status', 40)->default('confirmed')->after('status')->index();
                }
                if (!Schema::hasColumn('work_orders', 'is_draft_po')) {
                    $table->boolean('is_draft_po')->default(false)->after('lifecycle_status');
                }
                if (!Schema::hasColumn('work_orders', 'service_type')) {
                    $table->string('service_type', 60)->nullable()->after('order_type');
                }
                if (!Schema::hasColumn('work_orders', 'price_basis')) {
                    $table->string('price_basis', 190)->nullable()->after('payment_terms');
                }
                if (!Schema::hasColumn('work_orders', 'buyer_name')) {
                    $table->string('buyer_name', 190)->nullable()->after('user_department_person');
                }
                if (!Schema::hasColumn('work_orders', 'subject')) {
                    $table->string('subject', 255)->nullable()->after('title');
                }
                if (!Schema::hasColumn('work_orders', 'contract_tenure')) {
                    $table->string('contract_tenure', 120)->nullable()->after('completion_date');
                }
                if (!Schema::hasColumn('work_orders', 'defect_liability_months')) {
                    $table->unsignedInteger('defect_liability_months')->default(12)->after('retention_percent');
                }
                if (!Schema::hasColumn('work_orders', 'defect_liability_ends_on')) {
                    $table->date('defect_liability_ends_on')->nullable()->after('defect_liability_months');
                }
                if (!Schema::hasColumn('work_orders', 'retention_release_due_on')) {
                    $table->date('retention_release_due_on')->nullable()->after('defect_liability_ends_on');
                }
                if (!Schema::hasColumn('work_orders', 'retention_released_at')) {
                    $table->timestamp('retention_released_at')->nullable()->after('retention_release_due_on');
                }
                if (!Schema::hasColumn('work_orders', 'liquidated_damages')) {
                    $table->text('liquidated_damages')->nullable()->after('retention_released_at');
                }
                if (!Schema::hasColumn('work_orders', 'general_terms_text')) {
                    $table->longText('general_terms_text')->nullable()->after('terms_and_conditions');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('work_orders')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $cols = [
                    'lifecycle_status',
                    'is_draft_po',
                    'service_type',
                    'price_basis',
                    'buyer_name',
                    'subject',
                    'contract_tenure',
                    'defect_liability_months',
                    'defect_liability_ends_on',
                    'retention_release_due_on',
                    'retention_released_at',
                    'liquidated_damages',
                    'general_terms_text',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('work_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('work_order_uploads');
    }
};

