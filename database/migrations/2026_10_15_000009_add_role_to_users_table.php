<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32)->default('admin');
        });

        $configuredSuperAdmin = config('auth.super_admin_email');
        $superAdminId = $configuredSuperAdmin
            ? DB::table('users')->where('email', $configuredSuperAdmin)->value('id')
            : DB::table('users')->orderBy('id')->value('id');

        if ($superAdminId) {
            DB::table('users')->where('id', $superAdminId)->update(['role' => 'super_admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
