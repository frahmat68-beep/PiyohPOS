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
        Schema::create('daily_recaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->onDelete('cascade');
            $table->date('recap_date');
            $table->integer('total_orders')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('cash_revenue', 14, 2)->default(0);
            $table->decimal('midtrans_revenue', 14, 2)->default(0);
            $table->decimal('qris_revenue', 14, 2)->default(0);
            $table->decimal('other_revenue', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('service_charge_total', 14, 2)->default(0);
            $table->integer('cancelled_orders_count')->default(0);
            $table->json('payment_method_breakdown')->nullable();
            $table->json('items_summary')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['outlet_id', 'recap_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_recaps');
    }
};
