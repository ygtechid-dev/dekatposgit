<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPriceModeFieldsToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the "Mode Harga" selector (Satu Harga / Satuan / Level Harga /
     * Jumlah) plus the point/commission fields shown alongside the "Satu
     * Harga" (single-price) mode, which didn't exist on products before.
     * The markup_percent column backs the "Proc %" field for single-price
     * mode; per-unit/per-level/per-quantity-tier pricing lives in the new
     * product_units / product_unit_price_levels / product_unit_price_tiers
     * tables (see the migrations that follow this one).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('price_mode')->default('single')->after('wholesale_price');
            $table->double('markup_percent')->nullable()->after('price_mode');
            $table->double('point')->nullable()->after('markup_percent');
            $table->double('commission')->nullable()->after('point');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_mode', 'markup_percent', 'point', 'commission']);
        });
    }
}
