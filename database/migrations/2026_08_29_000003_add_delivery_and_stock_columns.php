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
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('completed_at');
            $table->foreignId('delivered_by_user_id')->nullable()->after('delivered_at')->constrained('users')->nullOnDelete();
            $table->string('midtrans_transaction_id')->nullable()->after('payment_method');
            $table->string('midtrans_payment_type')->nullable()->after('midtrans_transaction_id');
            $table->string('midtrans_snap_token')->nullable()->after('midtrans_payment_type');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_quantity')->nullable()->after('base_price');
            $table->integer('low_stock_threshold')->default(5)->after('stock_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivered_by_user_id');
            $table->dropColumn([
                'delivered_at',
                'midtrans_transaction_id',
                'midtrans_payment_type',
                'midtrans_snap_token',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'low_stock_threshold']);
        });
    }
};
