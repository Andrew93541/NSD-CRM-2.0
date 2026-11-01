<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('work_orders', 'contractor_name')) {
                $table->string('contractor_name', 150)->nullable()->after('client_reference');
            }
            if (!Schema::hasColumn('work_orders', 'contractor_gst')) {
                $table->string('contractor_gst', 40)->nullable()->after('contractor_name');
            }
            if (!Schema::hasColumn('work_orders', 'contractor_contact')) {
                $table->string('contractor_contact', 80)->nullable()->after('contractor_gst');
            }
            if (!Schema::hasColumn('work_orders', 'payment_terms')) {
                $table->string('payment_terms', 255)->nullable()->after('contractor_contact');
            }
            if (!Schema::hasColumn('work_orders', 'boq_items')) {
                $table->json('boq_items')->nullable()->after('scope_of_work');
            }
            if (!Schema::hasColumn('work_orders', 'terms_and_conditions')) {
                $table->text('terms_and_conditions')->nullable()->after('boq_items');
            }
        });

        Schema::table('sites', function (Blueprint $table) {
            if (!Schema::hasColumn('sites', 'kiosk_token')) {
                $table->string('kiosk_token', 64)->nullable()->unique()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $dropCols = array_filter(
                ['contractor_name', 'contractor_gst', 'contractor_contact', 'payment_terms', 'boq_items', 'terms_and_conditions'],
                fn ($c) => Schema::hasColumn('work_orders', $c)
            );
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });

        Schema::table('sites', function (Blueprint $table) {
            if (Schema::hasColumn('sites', 'kiosk_token')) {
                $table->dropColumn('kiosk_token');
            }
        });
    }
};

