<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDocumentToExpensesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('expenses', 'document')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('document')->nullable()->after('note');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('expenses', 'document')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('document');
            });
        }
    }
}
