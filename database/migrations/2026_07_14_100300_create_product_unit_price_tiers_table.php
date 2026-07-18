<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductUnitPriceTiersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * "Jumlah" (quantity-break) mode: up to 4 tiers per (product, unit),
     * e.g. buy >=1 at Harga 1, >=12 at Harga 2, etc. tier_no is 1-4 and
     * min_qty is the quantity threshold that activates that tier's price.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_unit_price_tiers', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_unit_id');
            $table->unsignedTinyInteger('tier_no');
            $table->double('min_qty')->nullable();
            $table->double('markup_percent')->nullable();
            $table->double('price')->nullable();
            $table->timestamps();

            $table->unique(['product_unit_id', 'tier_no'], 'puptier_unit_tier_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_unit_price_tiers');
    }
}
