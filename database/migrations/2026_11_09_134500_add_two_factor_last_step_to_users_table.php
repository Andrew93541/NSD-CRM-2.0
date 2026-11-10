<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'two_factor_last_step')) {
                $table->unsignedBigInteger('two_factor_last_step')
                    ->nullable()
                    ->after('two_factor_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'two_factor_last_step')) {
                $table->dropColumn('two_factor_last_step');
            }
        });
    }
};

