<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateChartOfAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // asset, liability, equity, revenue, expense
            $table->string('normal_balance'); // debit, credit
            $table->integer('parent_id')->nullable();
            $table->double('opening_balance')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(0);
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });

        $now = date('Y-m-d H:i:s');
        $accounts = [
            // Assets
            ['1000', 'ASET', 'asset', 'debit', null],
            ['1100', 'Kas & Bank', 'asset', 'debit', null],
            ['1101', 'Kas', 'asset', 'debit', null],
            ['1200', 'Piutang Usaha', 'asset', 'debit', null],
            ['1300', 'Persediaan Barang Dagang', 'asset', 'debit', null],
            ['1400', 'Titipan / Deposit ke Supplier', 'asset', 'debit', null],
            // Liabilities
            ['2000', 'KEWAJIBAN', 'liability', 'credit', null],
            ['2100', 'Hutang Usaha', 'liability', 'credit', null],
            ['2200', 'Titipan / Deposit Pelanggan', 'liability', 'credit', null],
            // Equity
            ['3000', 'MODAL', 'equity', 'credit', null],
            ['3100', 'Modal Pemilik', 'equity', 'credit', null],
            ['3200', 'Laba Ditahan', 'equity', 'credit', null],
            // Revenue
            ['4000', 'PENDAPATAN', 'revenue', 'credit', null],
            ['4100', 'Penjualan', 'revenue', 'credit', null],
            ['4200', 'Retur Penjualan', 'revenue', 'debit', null],
            ['4300', 'Diskon Penjualan', 'revenue', 'debit', null],
            ['4900', 'Pendapatan Lain-lain', 'revenue', 'credit', null],
            // Expense / COGS
            ['5000', 'BEBAN', 'expense', 'debit', null],
            ['5100', 'Harga Pokok Penjualan (HPP)', 'expense', 'debit', null],
            ['5200', 'Beban Operasional', 'expense', 'debit', null],
            ['5300', 'Beban Gaji', 'expense', 'debit', null],
            ['5400', 'Retur Pembelian', 'expense', 'credit', null],
            ['5900', 'Beban Lain-lain', 'expense', 'debit', null],
        ];

        $rows = [];
        foreach ($accounts as $a) {
            $rows[] = [
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
            ];
        }
        DB::table('chart_of_accounts')->insert($rows);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chart_of_accounts');
    }
}
