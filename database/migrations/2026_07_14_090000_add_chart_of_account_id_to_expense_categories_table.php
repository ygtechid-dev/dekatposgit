<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddChartOfAccountIdToExpenseCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Lets each Expense Category (Kategori Biaya) point at its own "Beban"
     * (expense-type) Chart of Account, so posting an Expense debits the
     * correct account instead of always hitting the generic "Beban
     * Operasional" (5200) account.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->integer('chart_of_account_id')->nullable()->after('name');
        });

        // Default every existing category to "Beban Operasional" (5200) so
        // expense posting keeps working exactly as before until an admin
        // picks a more specific account per category.
        $beban = DB::table('chart_of_accounts')->where('code', '5200')->first();
        if ($beban) {
            DB::table('expense_categories')->whereNull('chart_of_account_id')->update([
                'chart_of_account_id' => $beban->id,
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
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn('chart_of_account_id');
        });
    }
}
