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
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('status');
            $table->string('locked_by_device', 64)->nullable()->after('is_locked');
            $table->timestamp('locked_at')->nullable()->after('locked_by_device');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_locked', 'locked_by_device', 'locked_at']);
        });
    }
};
