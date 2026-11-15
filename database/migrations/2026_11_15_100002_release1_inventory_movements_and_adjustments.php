<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_transactions')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('inventory_transactions', 'work_order_id')) {
                    $table->foreignId('work_order_id')
                        ->nullable()
                        ->after('purchase_order_id')
                        ->constrained('work_orders')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('inventory_transactions', 'adjustment_reason')) {
                    $table->string('adjustment_reason', 255)->nullable()->after('status');
                }

                if (! Schema::hasColumn('inventory_transactions', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('adjustment_reason');
                }
            });
        }

        if (Schema::hasTable('inventory_transaction_items')) {
            Schema::table('inventory_transaction_items', function (Blueprint $table) {
                if (! Schema::hasColumn('inventory_transaction_items', 'previous_quantity')) {
                    $table->decimal('previous_quantity', 14, 2)->nullable()->after('unit_cost');
                }

                if (! Schema::hasColumn('inventory_transaction_items', 'resulting_quantity')) {
                    $table->decimal('resulting_quantity', 14, 2)->nullable()->after('previous_quantity');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_transaction_items')) {
            Schema::table('inventory_transaction_items', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_transaction_items', 'resulting_quantity')) {
                    $table->dropColumn('resulting_quantity');
                }
                if (Schema::hasColumn('inventory_transaction_items', 'previous_quantity')) {
                    $table->dropColumn('previous_quantity');
                }
            });
        }

        if (Schema::hasTable('inventory_transactions')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_transactions', 'work_order_id')) {
                    $table->dropConstrainedForeignId('work_order_id');
                }
                if (Schema::hasColumn('inventory_transactions', 'rejection_reason')) {
                    $table->dropColumn('rejection_reason');
                }
                if (Schema::hasColumn('inventory_transactions', 'adjustment_reason')) {
                    $table->dropColumn('adjustment_reason');
                }
            });
        }
    }
};
