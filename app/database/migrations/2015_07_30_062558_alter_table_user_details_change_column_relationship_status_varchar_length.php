<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableUserDetailsChangeColumnRelationshipStatusVarcharLength extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // change relationship_status varchar length from 10 to 30
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` MODIFY `relationship_status` VARCHAR(30) NULL DEFAULT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` MODIFY `relationship_status` VARCHAR(10) NULL DEFAULT NULL');
    }

}
