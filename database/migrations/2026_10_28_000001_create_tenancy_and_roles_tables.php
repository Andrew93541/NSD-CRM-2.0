<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 50);
            $table->json('permissions')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('status', 20)->default('active'); // active, inactive
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('client_name')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('active'); // active, on_hold, completed, archived
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('location')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('active'); // active, paused, closed
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('user_site', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('role_in_site', 30)->default('supervisor'); // supervisor, manager, storekeeper, engineer
            $table->timestamps();

            $table->unique(['user_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_site');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('companies');
    }
};
