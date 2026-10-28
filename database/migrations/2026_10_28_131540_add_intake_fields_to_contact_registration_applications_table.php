<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contact_registration_applications', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('organization')->nullable()->after('phone');
            $table->string('role')->nullable()->after('organization');
            $table->string('department')->nullable()->after('role');
            $table->string('affiliation_type')->nullable()->after('department');
            $table->string('track_stream')->nullable()->after('affiliation_type');
            $table->json('domain_expertise')->nullable()->after('application_data');
            $table->json('technical_skills')->nullable()->after('domain_expertise');
            $table->json('tools_methods')->nullable()->after('technical_skills');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_registration_applications', function (Blueprint $table) {
            $table->dropColumn(['phone', 'organization', 'role', 'department', 'affiliation_type', 'track_stream', 'domain_expertise', 'technical_skills', 'tools_methods']);
        });
    }
};
