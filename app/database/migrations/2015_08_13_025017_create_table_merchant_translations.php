<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableMerchantTranslations extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('merchant_translations', function(Blueprint $table)
		{
			$table->increments('merchant_translation_id');
			$table->integer('merchant_id')->unsigned();
			$table->integer('merchant_language_id')->unsigned();
			$table->string('name', 100)->nullable();
			$table->text('description')->nullable();
			$table->text('ticket_header')->nullable();
			$table->text('ticket_footer')->nullable();
			$table->string('status', 15)->default('active');
			$table->timestamps();
			$table->index(['merchant_id', 'merchant_language_id'], 'merchant_language_idx');
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
		Schema::drop('merchant_translations');
	}

}
