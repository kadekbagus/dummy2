<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableMerchantLanguages extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('merchant_languages', function(Blueprint $table)
		{
			$table->increments('merchant_language_id');
			$table->integer('language_id')->unsigned();
			$table->integer('merchant_id')->unsigned();
			$table->timestamps();
			$table->string('status', 15)->default('active');
			$table->index(['merchant_id'], 'merchant_idx');
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
		Schema::drop('merchant_languages');
	}

}
