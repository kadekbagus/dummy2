<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableLanguagesAddNativeNameAndStatus extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::table('languages', function(Blueprint $table)
        {
            $table->string('name_native', 100)->nullable()->after('name');
            $table->string('status', 20)->nullable()->after('updated_at');
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::table('languages', function(Blueprint $table)
        {
            $table->dropColumn('name_native');
            $table->dropColumn('status');
        });
	}

}
