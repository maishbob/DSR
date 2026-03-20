<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained()->onDelete('set null');
            $table->foreignId('station_id')->nullable()->after('owner_id')->constrained()->onDelete('set null');
            $table->enum('role', ['owner', 'manager', 'operator', 'accountant'])->default('operator')->after('station_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['station_id']);
            $table->dropColumn(['owner_id', 'station_id', 'role']);
        });
    }
};
