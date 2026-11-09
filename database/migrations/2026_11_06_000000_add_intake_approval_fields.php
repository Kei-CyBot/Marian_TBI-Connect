<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startups', function (Blueprint $table): void {
            if (! Schema::hasColumn('startups', 'organization')) {
                $table->string('organization')->nullable();
            }
            if (! Schema::hasColumn('startups', 'description')) {
                $table->text('description')->nullable();
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'affiliation')) {
                $table->string('affiliation')->nullable();
            }
            if (! Schema::hasColumn('users', 'startup_id')) {
                $table->foreignId('startup_id')->nullable()->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'startup_id')) {
                $table->dropConstrainedForeignId('startup_id');
            }
            if (Schema::hasColumn('users', 'affiliation')) {
                $table->dropColumn('affiliation');
            }
        });

        Schema::table('startups', function (Blueprint $table): void {
            $table->dropColumn(array_values(array_filter(
                ['organization', 'description'],
                fn (string $column): bool => Schema::hasColumn('startups', $column)
            )));
        });
    }
};
