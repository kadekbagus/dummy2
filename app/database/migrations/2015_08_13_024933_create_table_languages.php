<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableLanguages extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('languages', function(Blueprint $table)
		{
			$table->increments('language_id');
			$table->string('name', 50);
			$table->timestamps();
            $table->index(['name'], 'name_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('languages');
	}

}
