<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $table->string('two_factor_secret', 64)->nullable()->after('two_factor_enabled');
            }
            if (!Schema::hasColumn('users', 'two_factor_code')) {
                $table->string('two_factor_code', 255)->nullable()->after('two_factor_secret');
            }
            if (!Schema::hasColumn('users', 'two_factor_expires_at')) {
                $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
            }
            if (!Schema::hasColumn('users', 'two_factor_verified_at')) {
                $table->timestamp('two_factor_verified_at')->nullable()->after('two_factor_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('users', 'two_factor_enabled') ? 'two_factor_enabled' : null,
                Schema::hasColumn('users', 'two_factor_secret') ? 'two_factor_secret' : null,
                Schema::hasColumn('users', 'two_factor_code') ? 'two_factor_code' : null,
                Schema::hasColumn('users', 'two_factor_expires_at') ? 'two_factor_expires_at' : null,
                Schema::hasColumn('users', 'two_factor_verified_at') ? 'two_factor_verified_at' : null,
            ]);

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};

