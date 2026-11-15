<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('code', 40);
                $table->string('name', 255);
                $table->string('vendor_type', 50)->default('material_supplier');
                $table->string('contact_person', 150)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('gstin', 25)->nullable();
                $table->string('pan', 20)->nullable();
                $table->text('address')->nullable();
                $table->unsignedSmallInteger('payment_terms_days')->default(30);
                $table->string('bank_name', 150)->nullable();
                $table->string('bank_account_no', 60)->nullable();
                $table->string('bank_ifsc', 25)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'vendors_company_code_unique');
            });
        } elseif (! Schema::hasIndex('vendors', ['company_id', 'code'])) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->unique(['company_id', 'code'], 'vendors_company_code_unique');
            });
        }

        if (Schema::hasTable('clients') && ! Schema::hasIndex('clients', ['company_id', 'code'])) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unique(['company_id', 'code'], 'clients_company_code_unique');
            });
        }

        if (Schema::hasTable('purchase_orders') && ! Schema::hasColumn('purchase_orders', 'vendor_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreignId('vendor_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained('vendors')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('materials') && ! Schema::hasColumn('materials', 'hsn_code')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->string('hsn_code', 30)->nullable()->after('unit_of_measure');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'vendor_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('vendor_id');
            });
        }

        if (Schema::hasTable('materials') && Schema::hasColumn('materials', 'hsn_code')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->dropColumn('hsn_code');
            });
        }

        if (Schema::hasTable('clients') && Schema::hasIndex('clients', 'clients_company_code_unique')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropUnique('clients_company_code_unique');
            });
        }

        Schema::dropIfExists('vendors');
    }
};
