<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEventTranslationsAddColoumnImage extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('event_translations', function(Blueprint $table)
		{
            $table->string('image_translation', 255)->nullable()->after('status');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('event_translations', function(Blueprint $table)
		{
            $table->dropColumn('image_translation');
		});
	}

}
