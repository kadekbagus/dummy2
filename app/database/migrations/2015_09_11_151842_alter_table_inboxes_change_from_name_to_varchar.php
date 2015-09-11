<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableInboxesChangeFromNameToVarchar extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('inboxes', function(Blueprint $table)
		{	
			$prefix = DB::getTablePrefix();
	        DB::statement("ALTER TABLE `{$prefix}inboxes`
	                        MODIFY COLUMN `from_name` VARCHAR(20) NULL");
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('inboxes', function(Blueprint $table)
		{
			$prefix = DB::getTablePrefix();
	        DB::statement("ALTER TABLE `{$prefix}inboxes`
	                        MODIFY COLUMN `from_name` BIGINT NULL");
		});
	}

}
