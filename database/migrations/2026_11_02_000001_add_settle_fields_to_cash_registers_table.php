<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSettleFieldsToCashRegistersTable extends Migration
{
    public function up()
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->tinyInteger('settle_status')->default(0)->after('status')->comment('0=pending, 1=approved, 2=rejected');
            $table->string('approved_by')->nullable()->after('settle_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->timestamp('closed_at')->nullable()->after('approved_at');
            $table->double('settle_amount')->nullable()->after('closed_at');
        });
    }

    public function down()
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn(['settle_status', 'approved_by', 'approved_at', 'closed_at', 'settle_amount']);
        });
    }
}
