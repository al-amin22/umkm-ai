<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // orders: status filter + date range queries used in analytics and pesanan list
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['shop_id', 'status'], 'orders_shop_status_idx');
            $table->index(['shop_id', 'created_at'], 'orders_shop_created_idx');
        });

        // order_items: aggregate queries for top products report
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('product_id', 'order_items_product_idx');
        });

        // customers: segment filter + shop scope
        Schema::table('customers', function (Blueprint $table) {
            $table->index(['shop_id', 'rfm_segment'], 'customers_shop_segment_idx');
            $table->index(['shop_id', 'last_order_at'], 'customers_shop_last_order_idx');
        });

        // products: active products for storefront
        Schema::table('products', function (Blueprint $table) {
            $table->index(['shop_id', 'status'], 'products_shop_status_idx');
        });

        // stocks: critical stock alert query
        Schema::table('stocks', function (Blueprint $table) {
            $table->index('product_id', 'stocks_product_idx');
        });

        // payment_logs: webhook lookup by reference_id
        Schema::table('payment_logs', function (Blueprint $table) {
            $table->index('reference_id', 'payment_logs_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_shop_status_idx');
            $table->dropIndex('orders_shop_created_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_product_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_shop_segment_idx');
            $table->dropIndex('customers_shop_last_order_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_shop_status_idx');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex('stocks_product_idx');
        });

        Schema::table('payment_logs', function (Blueprint $table) {
            $table->dropIndex('payment_logs_reference_idx');
        });
    }
};
