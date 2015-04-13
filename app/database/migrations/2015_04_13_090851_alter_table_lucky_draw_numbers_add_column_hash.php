<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableLuckyDrawNumbersAddColumnHash extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lucky_draw_numbers', function(Blueprint $table)
        {
            $table->string('hash', 40)->nullable()->after('issued_date');

            $table->index(array('hash'), 'hash_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lucky_draw_numbers', function(Blueprint $table)
        {
            $table->dropIndex('hash_idx');

            $table->dropColumn('hash');
        });
    }

}
