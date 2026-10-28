<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('last_name');
            $table->string('gender', 20)->nullable()->after('date_of_birth');
            $table->string('alternate_phone', 30)->nullable()->after('phone');
            $table->string('email', 255)->nullable()->after('alternate_phone');
            $table->text('address')->nullable()->after('email');
            $table->string('department', 100)->nullable()->after('trade_designation');
            $table->string('emergency_contact_name', 150)->nullable()->after('joining_date');
            $table->string('emergency_contact_relation', 60)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_relation');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth',
                'gender',
                'alternate_phone',
                'email',
                'address',
                'department',
                'emergency_contact_name',
                'emergency_contact_relation',
                'emergency_contact_phone',
            ]);
        });
    }
};

