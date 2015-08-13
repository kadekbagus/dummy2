<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableCategoryTranslations extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_translations', function (Blueprint $table) {
            $table->increments('category_translation_id');
            $table->integer('category_id')->unsigned();
            $table->integer('merchant_language_id')->unsigned();
            $table->string('category_name', 100)->nullable();
            $table->string('description', 2000)->nullable();
            $table->string('status', 15)->default('active');
            $table->timestamps();
            $table->index(['category_id', 'merchant_language_id'], 'category_language_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('category_translations');
    }

}
