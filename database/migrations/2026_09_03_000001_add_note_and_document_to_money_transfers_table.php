<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNoteAndDocumentToMoneyTransfersTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('money_transfers', 'note')) {
            Schema::table('money_transfers', function (Blueprint $table) {
                $table->text('note')->nullable()->after('amount');
            });
        }

        if (!Schema::hasColumn('money_transfers', 'document')) {
            Schema::table('money_transfers', function (Blueprint $table) {
                $table->string('document')->nullable()->after('note');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('money_transfers', 'document')) {
            Schema::table('money_transfers', function (Blueprint $table) {
                $table->dropColumn('document');
            });
        }

        if (Schema::hasColumn('money_transfers', 'note')) {
            Schema::table('money_transfers', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }
}
