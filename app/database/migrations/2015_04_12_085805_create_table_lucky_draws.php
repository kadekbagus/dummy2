<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableLuckyDraws extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lucky_draws', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('lucky_draw_id');
            $table->integer('mall_id')->unsigned();
            $table->string('lucky_draw_name', 255);
            $table->string('description', 2000)->nullable();
            $table->string('image', 255)->nullable();
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->decimal('minimum_amount', 16, 2)->nullable()->default('0');
            $table->datetime('grace_period_date')->nullable();
            $table->smallInteger('grace_period_in_days')->nullable()->default('0');
            $table->integer('min_number')->unsigned()->nullable()->default('0');
            $table->integer('max_number')->unsigned()->nullable()->default('0');
            $table->string('status', 15)->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();
            $table->timestamps();

            $table->index(array('mall_id'), 'mall_id_idx');
            $table->index(array('lucky_draw_name'), 'lucky_draw_name_idx');
            $table->index(array('start_date', 'end_date'), 'startdate_enddate_idx');
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
        Schema::drop('lucky_draws');
    }

}
