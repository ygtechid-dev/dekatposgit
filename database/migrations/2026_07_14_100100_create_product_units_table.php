<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductUnitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per (product, unit) combination the product may be priced in
     * when price_mode is "unit", "level", or "qty". Mirrors the units
     * already listed in products.product_sale_unit_ids, but adds real
     * per-unit pricing columns (cost/markup/price/point/commission) instead
     * of just a comma-separated id list.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id');
            $table->integer('unit_id');
            $table->double('cost')->nullable();
            $table->double('markup_percent')->nullable();
            $table->double('price')->nullable();
            $table->double('point')->nullable();
            $table->double('commission')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_units');
    }
}
