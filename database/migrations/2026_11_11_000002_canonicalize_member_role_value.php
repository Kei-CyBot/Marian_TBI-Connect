<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'member')->update(['role' => 'member_incubatee']);
    }

    // shortcut: down() cannot restore the original 'member' rows, so it is a no-op
    public function down(): void
    {
    }
};
