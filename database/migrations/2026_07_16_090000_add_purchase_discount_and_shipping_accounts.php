<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddPurchaseDiscountAndShippingAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = date('Y-m-d H:i:s');
        $accounts = [
            // Contra-expense: credited when a purchase carries an order-level
            // discount, so Persediaan is recorded gross and this account
            // (which rolls into net income -> equity, like Retur Pembelian)
            // absorbs the discount instead of quietly reducing inventory cost.
            ['5410', 'Diskon / Potongan Pembelian', 'expense', 'credit', null],
            // Debited separately from Persediaan for shipping/other costs
            // billed on a purchase, so freight never gets silently folded
            // into inventory value.
            ['5210', 'Biaya Kirim Pembelian', 'expense', 'debit', null],
        ];

        foreach ($accounts as $a) {
            $exists = DB::table('chart_of_accounts')->where('code', $a[0])->exists();
            if (!$exists) {
                DB::table('chart_of_accounts')->insert([
                    'code' => $a[0],
                    'name' => $a[1],
                    'type' => $a[2],
                    'normal_balance' => $a[3],
                    'parent_id' => $a[4],
                    'opening_balance' => 0,
                    'description' => null,
                    'is_default' => 1,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
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
        DB::table('chart_of_accounts')->whereIn('code', ['5410', '5210'])->delete();
    }
}
