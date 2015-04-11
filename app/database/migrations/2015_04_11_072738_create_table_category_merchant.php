<?php
/**
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableCategoryMerchant extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_merchant', function(Blueprint $table)
        {
            $table->increments('category_merchant_id');
            $table->integer('category_id')->unsigned()->nullable()->default(NULL);
            $table->bigInteger('merchant_id')->unsigned()->nullable()->default(NULL);
            $table->timestamps();

            $table->index(array('category_id'), 'category_idx');
            $table->index(array('merchant_id'), 'merchant_idx');
            $table->index(array('merchant_id'), 'category_merchant_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('category_merchant');
    }

}
