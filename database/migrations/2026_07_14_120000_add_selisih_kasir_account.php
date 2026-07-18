<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddSelisihKasirAccount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $exists = DB::table('chart_of_accounts')->where('code', '2300')->exists();
        if (!$exists) {
            $now = date('Y-m-d H:i:s');
            DB::table('chart_of_accounts')->insert([
                'code' => '2300',
                'name' => 'Selisih Kasir (Penyesuaian Stok)',
                'type' => 'liability',
                'normal_balance' => 'credit',
                'parent_id' => null,
                'opening_balance' => 0,
                'description' => 'Selisih stok fisik vs sistem hasil penyesuaian stok',
                'is_default' => 1,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('chart_of_accounts')->where('code', '2300')->delete();
    }
}
