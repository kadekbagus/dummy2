<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableNewsMerchant extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('news_merchant', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('news_merchant_id');
            $table->integer('news_id')->unsigned();
            $table->integer('merchant_id')->unsigned();
            $table->string('object_type', 15)->nullable();

            $table->index(array('news_id'), 'news_id_idx');
            $table->index(array('merchant_id'), 'merchant_id_idx');
            $table->index(array('object_type'), 'object_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('news_merchant');
    }

}
