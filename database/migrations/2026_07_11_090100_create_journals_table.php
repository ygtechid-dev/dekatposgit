<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateJournalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->increments('id');
            $table->string('journal_no');
            $table->date('journal_date');
            $table->text('description')->nullable();
            $table->string('reference_type')->nullable(); // sale, purchase, expense, payroll, money_transfer, return, return_purchase, deposit, manual
            $table->integer('reference_id')->nullable();
            $table->boolean('is_manual')->default(0);
            $table->integer('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('journals');
    }
}
