<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ra_bills')) {
            Schema::create('ra_bills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

                $table->unsignedInteger('ra_number');
                $table->string('ra_label', 40); // e.g. 1ST RA BILL, 13TH RA BILL
                $table->string('invoice_number', 80)->nullable();
                $table->date('invoice_date')->nullable();
                $table->date('period_from');
                $table->date('period_to');
                $table->unsignedInteger('work_order_revision')->default(0);

                $table->decimal('previous_bill_amount', 16, 2)->default(0);
                $table->decimal('current_bill_amount', 16, 2)->default(0);
                $table->decimal('cumulative_bill_amount', 16, 2)->default(0);
                $table->decimal('excess_unbilled_amount', 16, 2)->default(0);

                $table->decimal('cgst_rate', 5, 2)->default(9.00);
                $table->decimal('sgst_rate', 5, 2)->default(9.00);
                $table->decimal('cgst_amount', 16, 2)->default(0);
                $table->decimal('sgst_amount', 16, 2)->default(0);
                $table->decimal('gross_amount', 16, 2)->default(0);

                $table->decimal('retention_percent', 5, 2)->default(0);
                $table->decimal('retention_deduction', 16, 2)->default(0);
                $table->decimal('advance_recovery', 16, 2)->default(0);
                $table->decimal('other_deductions', 16, 2)->default(0);
                $table->string('rounding_mode', 20)->default('paisa'); // paisa | rupee
                $table->decimal('net_payable', 16, 2)->default(0);

                $table->string('status', 30)->default('draft'); // draft, submitted, site_manager_approved, approved, invoiced, paid, rejected
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('site_manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('site_manager_approved_at')->nullable();
                $table->text('site_manager_notes')->nullable();
                $table->foreignId('senior_manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('senior_manager_approved_at')->nullable();
                $table->text('senior_manager_notes')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->timestamps();

                $table->unique(['work_order_id', 'ra_number'], 'ra_bills_wo_ra_number_unique');
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('ra_bill_items')) {
            Schema::create('ra_bill_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('ra_bill_id')->constrained('ra_bills')->cascadeOnDelete();
                $table->foreignId('work_order_item_id')->constrained('work_order_items')->cascadeOnDelete();

                $table->string('sr_no', 30);
                $table->string('service_code', 60)->nullable();
                $table->string('sac_code', 30)->nullable();
                $table->string('description');
                $table->text('specification')->nullable();
                $table->string('unit', 30)->default('M3');

                $table->decimal('ordered_quantity', 14, 3)->default(0);
                $table->decimal('rate', 14, 2)->default(0);
                $table->decimal('order_amount', 16, 2)->default(0);

                $table->decimal('previous_quantity', 14, 3)->default(0);
                $table->decimal('claimed_quantity', 14, 3)->default(0);
                $table->decimal('hold_quantity', 14, 3)->default(0);
                $table->decimal('effective_claimed_quantity', 14, 3)->default(0);
                $table->decimal('remaining_available_quantity', 14, 3)->default(0);
                $table->decimal('current_billable_quantity', 14, 3)->default(0);
                $table->decimal('excess_quantity', 14, 3)->default(0);
                $table->decimal('cumulative_quantity', 14, 3)->default(0);
                $table->decimal('balance_quantity', 14, 3)->default(0);

                $table->decimal('consumption_percent', 7, 2)->default(0);
                $table->string('warning_level', 20)->default('normal'); // normal, warning_80, warning_90, cap_100

                $table->decimal('previous_amount', 16, 2)->default(0);
                $table->decimal('current_amount', 16, 2)->default(0);
                $table->decimal('cumulative_amount', 16, 2)->default(0);
                $table->decimal('excess_amount', 16, 2)->default(0);

                $table->text('remarks')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['ra_bill_id', 'work_order_item_id'], 'ra_bill_items_bill_wo_item_unique');
            });
        }

        if (! Schema::hasTable('measurement_entries')) {
            Schema::create('measurement_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
                $table->foreignId('work_order_item_id')->constrained('work_order_items')->cascadeOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
                $table->foreignId('ra_bill_id')->nullable()->constrained('ra_bills')->nullOnDelete();

                $table->date('measured_on');
                $table->string('location_chainage', 190)->nullable();
                $table->decimal('nos', 12, 3)->default(1.000);
                $table->decimal('length', 12, 3)->nullable();
                $table->decimal('breadth', 12, 3)->nullable();
                $table->decimal('depth_height', 12, 3)->nullable();
                $table->decimal('direct_quantity', 14, 3)->nullable();
                $table->decimal('computed_quantity', 14, 3)->default(0);
                $table->decimal('hold_quantity', 14, 3)->default(0);

                $table->string('photo_path')->nullable();
                $table->text('remarks')->nullable();
                $table->string('status', 30)->default('verified'); // draft, verified, billed

                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();

                $table->index(['work_order_id', 'work_order_item_id', 'measured_on'], 'measurement_entries_wo_item_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_entries');
        Schema::dropIfExists('ra_bill_items');
        Schema::dropIfExists('ra_bills');
    }
};

