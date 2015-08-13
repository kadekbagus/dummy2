<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableLuckyDrawNumbersAddUniqueIndexForIdNumberStatus extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lucky_draw_numbers', function(Blueprint $table)
        {
            $table->unique(array('lucky_draw_id', 'lucky_draw_number_code', 'status'), 'luckydrawid_luckydrawnumbercode_status_unique');
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
            $table->dropUnique('luckydrawid_luckydrawnumbercode_status_unique');
        });
    }

}
