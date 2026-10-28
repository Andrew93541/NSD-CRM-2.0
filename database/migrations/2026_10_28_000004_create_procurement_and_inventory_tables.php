<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('location_type', 30)->default('central_warehouse'); // central_warehouse, site_store
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('material_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('unit_of_measure', 20); // Bags, MT, Cum, Litres, Units, Nos, Metres
            $table->decimal('minimum_stock_level', 12, 2)->default(0.00);
            $table->decimal('standard_unit_cost', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('quantity', 14, 2)->default(0.00);
            $table->decimal('reserved_quantity', 14, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['warehouse_id', 'material_id']);
            $table->index(['company_id', 'warehouse_id']);
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('request_number', 50);
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('urgency', 20)->default('normal'); // normal, urgent, emergency
            $table->boolean('is_emergency')->default(false);
            $table->text('emergency_justification')->nullable();
            
            // Workflow Status: pending -> approved / rejected -> ordered
            $table->string('status', 30)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_comments')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'request_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('quantity_requested', 12, 2);
            $table->decimal('estimated_unit_cost', 12, 2)->default(0.00);
            $table->text('purpose_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('po_number', 50);
            $table->string('supplier_name');
            $table->string('supplier_phone', 30)->nullable();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->string('status', 30)->default('issued'); // issued, partially_received, received, cancelled
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'po_number']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('quantity_ordered', 12, 2);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->decimal('quantity_received', 12, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('transaction_number', 50);
            $table->string('transaction_type', 30); // goods_receipt, warehouse_issue, site_return, inter_site_transfer
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('challan_or_invoice_no', 100)->nullable();
            $table->date('transaction_date');
            $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
            
            // Goods receipt verification
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('status', 30)->default('confirmed'); // pending_confirmation, confirmed, rejected
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'transaction_number']);
            $table->index(['company_id', 'transaction_type']);
        });

        Schema::create('inventory_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transaction_id')->constrained('inventory_transactions')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transaction_items');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('material_categories');
        Schema::dropIfExists('warehouses');
    }
};
