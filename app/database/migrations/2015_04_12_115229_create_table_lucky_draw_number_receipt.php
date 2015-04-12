<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableLuckyDrawNumberReceipt extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lucky_draw_number_receipt', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('lucky_draw_number_receipt_id');
            $table->bigInteger('lucky_draw_number_id')->unsigned();
            $table->bigInteger('lucky_draw_receipt_id')->unsigned();

            $table->index(array('lucky_draw_number_id'), 'lucky_draw_number_id_idx');
            $table->index(array('lucky_draw_receipt_id'), 'lucky_draw_receipt_id_idx');
            $table->index(array('lucky_draw_number_id', 'lucky_draw_receipt_id'), 'luckydrawnumberid_luckydrawreceiptid_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('lucky_draw_number_receipt');
    }

}
