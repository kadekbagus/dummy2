<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableMacAddresses extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('mac_addresses', function(Blueprint $table)
		{
			$table->engine = 'InnoDB';
			$table->bigIncrements('mac_address_id');
			$table->string('user_email', 255);
			$table->char('mac_address', 18);
			$table->timestamps();

			$table->index(array('user_email'), 'user_email_idx');
			$table->index(array('mac_address'), 'mac_address_idx');
			$table->index(array('user_email', 'mac_address'), 'user_email_mac_address_idx');
			$table->index(array('created_at'), 'created_at_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('mac_addresses');
	}

}
