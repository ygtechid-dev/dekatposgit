<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddIsCashAccountToChartOfAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Marks a Chart of Account row as a physical Kas/Bank account. When
     * checked in the Kode Akun form, the app auto-provisions/updates a
     * matching row in the legacy `accounts` table behind the scenes, so
     * every payment/expense/payroll/etc. dropdown across the app can keep
     * using `accounts.id` for storage while being managed from a single
     * "Kode Akun" screen (no separate "Tambah Akun" menu needed anymore).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->boolean('is_cash_account')->default(0)->after('is_default');
        });

        // Every COA that already has a linked `accounts` row (auto-created by
        // the 2026_07_11_090300 migration for pre-existing cash/bank accounts)
        // is, by definition, a cash/bank account — flag it so the Kode Akun
        // screen shows it as such.
        DB::table('chart_of_accounts')
            ->whereIn('id', function ($query) {
                $query->select('chart_of_account_id')
                    ->from('accounts')
                    ->whereNotNull('chart_of_account_id');
            })
            ->update(['is_cash_account' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn('is_cash_account');
        });
    }
}
