<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('label', 50);
            $table->timestamps();
        });

        $now = now();

        DB::table('roles')->insert([
            ['code' => 'staff', 'label' => 'Staff', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'approver', 'label' => 'Approver', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'admin', 'label' => 'Admin', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
