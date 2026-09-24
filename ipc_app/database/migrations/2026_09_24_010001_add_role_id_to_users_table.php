<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->restrictOnDelete();
        });

        // Backfill role_id from the old free-text role column, falling back to Staff for any
        // value that doesn't match a real role row (there shouldn't be any, but a hand-typed
        // varchar had no guarantee of that).
        $roleIdsByCode = DB::table('roles')->pluck('id', 'code');
        $staffRoleId = $roleIdsByCode['staff'];

        foreach ($roleIdsByCode as $code => $roleId) {
            DB::table('users')->where('role', $code)->update(['role_id' => $roleId]);
        }

        DB::table('users')->whereNull('role_id')->update(['role_id' => $staffRoleId]);

        // Enforce NOT NULL at the DB level on MySQL (production/dev). Skipped on sqlite (the test
        // suite's connection) since `ALTER ... MODIFY` is MySQL-specific and this app has no
        // doctrine/dbal installed for a portable Schema::table()->change(). The application layer
        // (User::booted()'s `creating` default, plus the `role` mutator always resolving a real
        // role_id) already guarantees this column is never left null either way.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY role_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('staff')->after('password');
            $table->index('role');
        });

        DB::statement('UPDATE users JOIN roles ON roles.id = users.role_id SET users.role = roles.code');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
