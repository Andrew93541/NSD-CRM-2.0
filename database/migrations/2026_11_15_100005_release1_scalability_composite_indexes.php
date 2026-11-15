<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Note: activity_logs(company_id, site_id, created_at), daily_attendances(company_id, site_id, attendance_date),
        // daily_attendances(company_id, worker_id, attendance_date), and tasks(company_id, site_id, status) already have
        // equivalent composite indexes from earlier migrations and are intentionally omitted here to prevent MySQL Warning 1831.
        $this->addIndexSafely('activity_logs', ['company_id', 'action', 'created_at'], 'idx_act_logs_comp_action_created');
        $this->addIndexSafely('purchase_orders', ['company_id', 'site_id', 'status'], 'idx_po_comp_site_status');
    }

    public function down(): void
    {
        $this->dropIndexSafely('purchase_orders', 'idx_po_comp_site_status');
        $this->dropIndexSafely('activity_logs', 'idx_act_logs_comp_action_created');
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addIndexSafely(string $table, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if (Schema::hasIndex($table, $columns) || Schema::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexSafely(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }
};
