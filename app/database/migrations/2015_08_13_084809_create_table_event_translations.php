<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableEventTranslations extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_translations', function (Blueprint $table) {
            $table->increments('event_translation_id');
            $table->integer('event_id')->unsigned();
            $table->integer('merchant_language_id')->unsigned();
            $table->string('event_name', 255)->nullable();
            $table->string('description', 2000)->nullable();
            $table->string('status', 15)->default('active');
            $table->timestamps();
            $table->index(['event_id', 'merchant_language_id'], 'event_language_idx');
            $table->index(['status'], 'status_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('event_translations');
    }

}
