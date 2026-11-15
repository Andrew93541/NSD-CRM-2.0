<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'must_change_password')) {
                    $table->boolean('must_change_password')->default(false)->after('is_active');
                }
                if (! Schema::hasColumn('users', 'password_changed_at')) {
                    $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
                }
                if (! Schema::hasColumn('users', 'last_login_at')) {
                    $col = $table->timestamp('last_login_at')->nullable();
                    if (Schema::hasColumn('users', 'two_factor_last_step')) {
                        $col->after('two_factor_last_step');
                    } elseif (Schema::hasColumn('users', 'two_factor_verified_at')) {
                        $col->after('two_factor_verified_at');
                    }
                }
                if (! Schema::hasColumn('users', 'last_login_ip')) {
                    $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
                }
            });
        }

        if (Schema::hasTable('company_user')) {
            Schema::table('company_user', function (Blueprint $table) {
                if (! Schema::hasColumn('company_user', 'permission_overrides')) {
                    $table->json('permission_overrides')->nullable()->after('is_primary');
                }
                if (! Schema::hasColumn('company_user', 'restrict_to_assigned_sites')) {
                    $table->boolean('restrict_to_assigned_sites')->default(false)->after('permission_overrides');
                }
            });
        }

        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (! Schema::hasColumn('roles', 'is_system')) {
                    $table->boolean('is_system')->default(false)->after('description');
                }
                if (! Schema::hasColumn('roles', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('is_system');
                }
            });
        }

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'is_system')) {
            DB::table('roles')
                ->whereIn('slug', [
                    'super_admin',
                    'company_admin',
                    'senior_manager',
                    'site_manager',
                    'site_supervisor',
                    'supervisor',
                    'warehouse_manager',
                    'office_staff',
                ])
                ->update(['is_system' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('roles', 'is_system')) {
                $drop[] = 'is_system';
            }
            if (Schema::hasColumn('roles', 'is_active')) {
                $drop[] = 'is_active';
            }
            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('company_user', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('company_user', 'permission_overrides')) {
                $drop[] = 'permission_overrides';
            }
            if (Schema::hasColumn('company_user', 'restrict_to_assigned_sites')) {
                $drop[] = 'restrict_to_assigned_sites';
            }
            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $drop = [];
            foreach (['must_change_password', 'password_changed_at', 'last_login_at', 'last_login_ip'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $drop[] = $col;
                }
            }
            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
