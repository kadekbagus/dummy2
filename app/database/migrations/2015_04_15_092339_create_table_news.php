<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableNews extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('news', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->increments('news_id');
            $table->integer('mall_id')->unsigned();
            $table->string('object_type', 15)->nullable();
            $table->string('news_name', 255);
            $table->string('description', 2000)->nullable();
            $table->string('image', 255)->nullable();
            $table->datetime('begin_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->tinyInteger('sticky_order')->nullable()->default('0');
            $table->string('link_object_type', 15)->nullable();
            $table->string('status', 15);
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();
            $table->timestamps();

            $table->index(array('mall_id'), 'mall_id_idx');
            $table->index(array('object_type'), 'object_type_idx');
            $table->index(array('news_name'), 'news_name_idx');
            $table->index(array('begin_date', 'end_date'), 'begindate_enddate_idx');
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
        Schema::drop('news');
    }

}
