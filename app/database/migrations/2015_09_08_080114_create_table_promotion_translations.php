<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTablePromotionTranslations extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotion_translations', function(Blueprint $table)
        {
            $table->increments('promotion_translation_id');
            $table->integer('promotion_id')->unsigned();
            $table->integer('merchant_language_id')->unsigned();
            $table->string('promotion_name', 255)->nullable();
            $table->string('description', 2000)->nullable();
            $table->string('status', 15)->default('active');
            $table->timestamps();
            $table->index(['promotion_id', 'merchant_language_id'], 'promotion_language_idx');
            $table->index(['status'], 'status_idx');
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();
            $table->index(['created_by'], 'created_by_idx');
            $table->index(['modified_by'], 'modified_by_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('promotion_translations');
    }

}
