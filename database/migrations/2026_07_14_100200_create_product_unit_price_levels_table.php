<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductUnitPriceLevelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * "Level Harga" mode: one row per (product_unit, customer_group) so each
     * unit can have a different sell price per Customer Group (e.g. Retail,
     * Grosir, Reseller) — the number of levels shown in the product form is
     * simply the number of active Customer Groups, not a fixed count.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_unit_price_levels', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_unit_id');
            $table->integer('customer_group_id');
            $table->double('markup_percent')->nullable();
            $table->double('price')->nullable();
            $table->timestamps();

            $table->unique(['product_unit_id', 'customer_group_id'], 'pupl_unit_group_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_unit_price_levels');
    }
}
