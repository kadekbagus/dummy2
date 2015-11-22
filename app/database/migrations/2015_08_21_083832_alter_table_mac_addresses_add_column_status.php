<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMacAddressesAddColumnStatus extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('mac_addresses', function(Blueprint $table)
		{
			$table->string('status', 15)->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('mac_addresses', function(Blueprint $table)
		{
			$table->dropColumn('status');
		});
	}

}
