<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableLuckyDrawWinners extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lucky_draw_winners', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('lucky_draw_winner_id');
            $table->bigInteger('lucky_draw_id')->unsigned();
            $table->string('lucky_draw_winner_code', 50);
            $table->tinyInteger('position')->nullable()->default('1');
            $table->bigInteger('lucky_draw_number_id')->unsigned()->nullable();
            $table->string('status', 15)->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();
            $table->timestamps();

            $table->index(array('lucky_draw_id'), 'lucky_draw_id_idx');
            $table->index(array('lucky_draw_winner_code'), 'lucky_draw_winner_code_idx');
            $table->index(array('lucky_draw_number_id'), 'lucky_draw_number_id_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('created_at'), 'created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('lucky_draw_winners');
    }

}
