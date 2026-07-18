<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddChartOfAccountIdToAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->integer('chart_of_account_id')->nullable()->after('id');
        });

        // Link every existing cash/bank Account to the default "Kas" COA (1101),
        // creating a dedicated child COA per account so each bank/cash account
        // gets its own ledger.
        $kas = DB::table('chart_of_accounts')->where('code', '1101')->first();
        if ($kas) {
            $accounts = DB::table('accounts')->get();
            $now = date('Y-m-d H:i:s');
            $seq = 1;
            foreach ($accounts as $account) {
                $code = '1101.' . $seq;
                $coaId = DB::table('chart_of_accounts')->insertGetId([
                    'code' => $code,
                    'name' => $account->name . ' (' . $account->account_no . ')',
                    'type' => 'asset',
                    'normal_balance' => 'debit',
                    'parent_id' => $kas->id,
                    'opening_balance' => $account->initial_balance ?? 0,
                    'description' => 'Auto-generated for account: ' . $account->name,
                    'is_default' => 0,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('accounts')->where('id', $account->id)->update(['chart_of_account_id' => $coaId]);
                $seq++;
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('chart_of_account_id');
        });
    }
}
