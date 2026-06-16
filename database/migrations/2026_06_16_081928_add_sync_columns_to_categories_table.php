<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('external_id', 100)->nullable()->unique()->after('id');
            $table->string('source_system', 50)->nullable()->default('piyohweb')->after('external_id');
            $table->timestamp('last_synced_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'source_system', 'last_synced_at']);
        });
    }
};
