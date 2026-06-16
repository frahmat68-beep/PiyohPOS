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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->onDelete('cascade');
            $table->foreignId('table_id')->nullable()->constrained('tables')->onDelete('cascade');
            $table->string('order_number', 50)->unique();
            $table->string('customer_name', 100)->nullable();
            $table->string('status', 20)->default('pending'); // pending, confirmed, preparing, ready, completed, cancelled
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('service_charge', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2);
            $table->string('accurate_sync_status', 20)->default('unsynced'); // unsynced, synced, failed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
