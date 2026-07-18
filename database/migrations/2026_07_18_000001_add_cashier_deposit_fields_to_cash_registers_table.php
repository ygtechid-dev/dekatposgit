<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCashierDepositFieldsToCashRegistersTable extends Migration
{
    public function up()
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->double('expected_cash_amount')->nullable()->after('status');
            $table->double('closing_cash_amount')->nullable()->after('expected_cash_amount');
            $table->double('deposit_amount')->nullable()->after('closing_cash_amount');
            $table->double('cash_difference')->nullable()->after('deposit_amount');
            $table->text('closing_note')->nullable()->after('cash_difference');
            $table->string('approval_status')->default('pending')->after('closing_note');
            $table->integer('approved_by')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_note')->nullable()->after('approved_at');
        });
    }

    public function down()
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn([
                'expected_cash_amount',
                'closing_cash_amount',
                'deposit_amount',
                'cash_difference',
                'closing_note',
                'approval_status',
                'approved_by',
                'approved_at',
                'approval_note',
            ]);
        });
    }
}
